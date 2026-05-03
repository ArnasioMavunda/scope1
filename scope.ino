// =============================================
// ESP32 - SCOPE - Controlo de Presença v5.0
// =============================================
//
// CORRECÇÕES v5.0:
//
// [FIX 1] FUSO HORÁRIO — configTime usava UTC+1 (3600s)
//   mas o servidor tinha time_zone="+00:00". Agora ambos
//   estão em WAT (UTC+1 = Africa/Luanda).
//   configTime(3600, 0, ...) mantido (correcto para Angola).
//
// [FIX 2] SOM DIFERENTE para RFID desconhecido
//   A API devolve "rfid_desconhecido":true quando o cartão
//   não está registado. O ESP32 detecta isso e toca um
//   padrão de bips diferente (3 bips curtos vs 1 bip longo).
//   Requer buzzer passivo/activo ligado ao PIN_BUZZER.
//
// [FIX 3] POLLING melhorado — retransmissão automática
//   Se o POST falhar, guarda na fila e reenvita nos
//   próximos 30 segundos (buffer de até 5 leituras).
//
// LIGAÇÕES HARDWARE (sem alterações):
//   RFID D0  → GPIO 18 (via optoacoplador PC817)
//   RFID D1  → GPIO 19 (via optoacoplador PC817)
//   LED Verde    → GPIO 2
//   LED Vermelho → GPIO 4
//   Buzzer       → GPIO 5  ← NOVO (liga ao GND com resistor 100Ω)
// =============================================

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <time.h>

// =================== CONFIGURAÇÃO ===================
const char* ssid      = "UNITEL_5G_7AA7F0";
const char* password  = "6Z6R767456";
const char* serverUrl = "http://192.168.0.56/scope/api/presenca.php";

// Fuso horário: WAT = UTC+1 (Africa/Luanda)
const long  gmtOffset_sec    = 3600;   // UTC+1
const int   daylightOffset_sec = 0;    // Angola não tem horário de verão

// Pinos
#define PIN_D0         18
#define PIN_D1         19
#define LED_VERDE       2
#define LED_VERMELHO    4
#define PIN_BUZZER      5   // Buzzer activo (sinal HIGH = liga)

// ====================================================
// Buffer de reenvio (leituras offline)
// ====================================================
struct LeituraBuffer {
  String rfid;
  String timestamp;
  bool pendente;
};
LeituraBuffer bufferOffline[5];
int bufferCount = 0;

// ====================================================
volatile uint32_t cardData   = 0;
volatile uint8_t  bitCount   = 0;
volatile unsigned long lastBitTime = 0;

void IRAM_ATTR handleD0() {
  cardData <<= 1;
  bitCount++;
  lastBitTime = millis();
}
void IRAM_ATTR handleD1() {
  cardData <<= 1;
  cardData |= 1;
  bitCount++;
  lastBitTime = millis();
}

// ── Piscar LED ────────────────────────────────────
void piscar(int pino, int vezes, int ms = 200) {
  for (int i = 0; i < vezes; i++) {
    digitalWrite(pino, HIGH); delay(ms);
    digitalWrite(pino, LOW);  delay(ms);
  }
}

// ── Buzzer ────────────────────────────────────────
// Som de sucesso: 1 bip longo
void bipSucesso() {
  digitalWrite(PIN_BUZZER, HIGH);
  delay(500);
  digitalWrite(PIN_BUZZER, LOW);
  delay(100);
}

// Som de atraso: 2 bips médios
void bipAtraso() {
  for (int i = 0; i < 2; i++) {
    digitalWrite(PIN_BUZZER, HIGH); delay(250);
    digitalWrite(PIN_BUZZER, LOW);  delay(150);
  }
}

// Som de RFID DESCONHECIDO: 3 bips curtos e rápidos
// Este padrão é diferente dos outros para o utilizador
// perceber imediatamente que o cartão não está registado
void bipDesconhecido() {
  for (int i = 0; i < 3; i++) {
    digitalWrite(PIN_BUZZER, HIGH); delay(100);
    digitalWrite(PIN_BUZZER, LOW);  delay(100);
  }
}

// Som de erro/sem ligação: 1 bip longo grave (intermitente)
void bipErro() {
  for (int i = 0; i < 2; i++) {
    digitalWrite(PIN_BUZZER, HIGH); delay(400);
    digitalWrite(PIN_BUZZER, LOW);  delay(200);
  }
}

// ── Obter timestamp ───────────────────────────────
String getTimestamp() {
  time_t now;
  struct tm timeinfo;
  time(&now);
  localtime_r(&now, &timeinfo);

  // Verificar se NTP já sincronizou (ano < 2020 = não sincronizado)
  if (timeinfo.tm_year + 1900 < 2020) {
    Serial.println("⚠️  NTP não sincronizado! A tentar novamente...");
    configTime(gmtOffset_sec, daylightOffset_sec,
               "pool.ntp.org", "time.google.com", "time.cloudflare.com");
    delay(2000);
    time(&now);
    localtime_r(&now, &timeinfo);
  }

  char ts[20];
  strftime(ts, sizeof(ts), "%Y-%m-%d %H:%M:%S", &timeinfo);
  return String(ts);
}

// ── Guardar leitura no buffer offline ────────────
void guardarNoBuffer(String rfid, String ts) {
  if (bufferCount < 5) {
    bufferOffline[bufferCount] = {rfid, ts, true};
    bufferCount++;
    Serial.println("💾 Guardado no buffer offline [" + String(bufferCount) + "/5]");
  } else {
    Serial.println("⚠️  Buffer cheio! Leitura perdida.");
  }
}

// ── Processar resposta da API ─────────────────────
void processarResposta(String resposta, int httpCode) {
  if (httpCode != 200) {
    Serial.println("❌ Erro HTTP " + String(httpCode));
    piscar(LED_VERMELHO, 2, 300);
    bipErro();
    return;
  }

  // Verificar flag rfid_desconhecido PRIMEIRO
  if (resposta.indexOf("\"rfid_desconhecido\":true") >= 0) {
    Serial.println("🚫 CARTÃO NÃO REGISTADO — som diferente!");
    piscar(LED_VERMELHO, 3, 100);
    bipDesconhecido();       // ← 3 bips curtos (distinto)
    return;
  }

  if (resposta.indexOf("\"presente\"") >= 0 &&
      resposta.indexOf("\"estado\"") >= 0) {
    // Distinguir estado dentro do JSON
    if (resposta.indexOf("\"estado\":\"presente\"") >= 0) {
      Serial.println("✅ PRESENTE");
      piscar(LED_VERDE, 1, 600);
      bipSucesso();
    } else if (resposta.indexOf("\"estado\":\"atraso\"") >= 0) {
      Serial.println("⏱  ATRASO");
      piscar(LED_VERDE, 2, 300);
      bipAtraso();
    } else if (resposta.indexOf("\"estado\":\"ausente\"") >= 0) {
      Serial.println("❌ FORA DO PRAZO");
      piscar(LED_VERMELHO, 1, 600);
      bipErro();
    }
  } else if (resposta.indexOf("\"saida\"") >= 0) {
    Serial.println("🚪 SAÍDA registada");
    piscar(LED_VERDE, 3, 200);
    bipSucesso();
  } else if (resposta.indexOf("\"aviso\"") >= 0) {
    Serial.println("⚠️  Fora de horário ou fim-de-semana");
    piscar(LED_VERMELHO, 2, 200);
    bipErro();
  } else {
    piscar(LED_VERDE, 1, 400);
    bipSucesso();
  }
}

// ── Enviar para API ───────────────────────────────
bool enviarPresenca(String rfidDecimal, String timestamp) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("❌ WiFi desligado! Guardando no buffer...");
    guardarNoBuffer(rfidDecimal, timestamp);
    piscar(LED_VERMELHO, 3, 150);
    bipErro();
    return false;
  }

  Serial.println("─────────────────────────────────");
  Serial.println("📡 A enviar para: " + String(serverUrl));
  Serial.println("   RFID:      " + rfidDecimal);
  Serial.println("   Timestamp: " + timestamp);

  HTTPClient http;
  http.begin(serverUrl);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(8000);

  String json = "{\"rfid\":\"" + rfidDecimal + "\",\"timestamp\":\"" + timestamp + "\"}";
  int code    = http.POST(json);

  if (code > 0) {
    String resposta = http.getString();
    Serial.println("   HTTP " + String(code) + " → " + resposta);
    processarResposta(resposta, code);
    http.end();
    Serial.println("─────────────────────────────────");
    return true;
  } else {
    Serial.println("❌ Sem resposta do servidor (código: " + String(code) + ")");
    Serial.println("   Verificar: XAMPP ligado? IP correcto? WiFi OK?");
    guardarNoBuffer(rfidDecimal, timestamp);
    piscar(LED_VERMELHO, 3, 200);
    bipErro();
    http.end();
    Serial.println("─────────────────────────────────");
    return false;
  }
}

// ── Reenviar buffer offline ───────────────────────
void reenviarBuffer() {
  if (bufferCount == 0) return;
  if (WiFi.status() != WL_CONNECTED) return;

  Serial.println("🔄 A reenviar " + String(bufferCount) + " leituras offline...");

  for (int i = 0; i < bufferCount; i++) {
    if (!bufferOffline[i].pendente) continue;

    HTTPClient http;
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(8000);

    String json = "{\"rfid\":\"" + bufferOffline[i].rfid +
                  "\",\"timestamp\":\"" + bufferOffline[i].timestamp + "\"}";
    int code = http.POST(json);

    if (code > 0) {
      bufferOffline[i].pendente = false;
      Serial.println("✅ Buffer[" + String(i) + "] reenviado.");
    } else {
      Serial.println("❌ Buffer[" + String(i) + "] falhou novamente.");
    }
    http.end();
    delay(500);
  }

  // Limpar entradas enviadas
  int novoCount = 0;
  for (int i = 0; i < bufferCount; i++) {
    if (bufferOffline[i].pendente) {
      bufferOffline[novoCount++] = bufferOffline[i];
    }
  }
  bufferCount = novoCount;
}

// ====================================================
void setup() {
  Serial.begin(115200);
  delay(500);

  pinMode(LED_VERDE,    OUTPUT);
  pinMode(LED_VERMELHO, OUTPUT);
  pinMode(PIN_BUZZER,   OUTPUT);
  digitalWrite(LED_VERDE,    LOW);
  digitalWrite(LED_VERMELHO, LOW);
  digitalWrite(PIN_BUZZER,   LOW);

  // Bip de arranque
  bipSucesso();
  piscar(LED_VERDE, 2, 100);

  // ── WiFi ───────────────────────────────────────
  Serial.println("\n=== SCOPE ESP32 v5.0 ===");
  Serial.println("Fuso horário: WAT (UTC+1) — Africa/Luanda");
  Serial.println("A ligar ao WiFi: " + String(ssid));
  WiFi.begin(ssid, password);

  int tentativas = 0;
  while (WiFi.status() != WL_CONNECTED && tentativas < 20) {
    delay(500);
    Serial.print(".");
    tentativas++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✅ WiFi ligado!");
    Serial.println("   IP do ESP32: " + WiFi.localIP().toString());
    piscar(LED_VERDE, 3, 100);
  } else {
    Serial.println("\n❌ FALHOU WiFi! Modo offline activado.");
    piscar(LED_VERMELHO, 5, 200);
  }

  // ── NTP — fuso horário WAT = UTC+1 ─────────────
  Serial.println("A sincronizar hora NTP (WAT = UTC+1)...");
  configTime(gmtOffset_sec, daylightOffset_sec,
             "pool.ntp.org", "time.google.com", "time.cloudflare.com");

  int ntpEspera = 0;
  struct tm timeinfo;
  while (!getLocalTime(&timeinfo) && ntpEspera < 20) {
    delay(500);
    Serial.print(".");
    ntpEspera++;
  }

  if (timeinfo.tm_year + 1900 >= 2020) {
    char ts[20];
    strftime(ts, sizeof(ts), "%Y-%m-%d %H:%M:%S", &timeinfo);
    Serial.println("\n✅ Hora sincronizada (WAT): " + String(ts));
  } else {
    Serial.println("\n⚠️  NTP falhou. Timestamps incorrectos até sincronizar.");
    piscar(LED_VERMELHO, 3, 100);
  }

  // ── RFID ───────────────────────────────────────
  pinMode(PIN_D0, INPUT_PULLUP);
  pinMode(PIN_D1, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(PIN_D0), handleD0, FALLING);
  attachInterrupt(digitalPinToInterrupt(PIN_D1), handleD1, FALLING);

  Serial.println("\n=== PRONTO — Aguarda cartão ===\n");
}

void loop() {
  // ── Leitura do cartão (26 bits Wiegand) ────────
  if (bitCount == 26 && (millis() - lastBitTime > 100)) {

    uint32_t rfidID = (cardData >> 1) & 0xFFFFFF;

    char rfidDec[12];
    sprintf(rfidDec, "%lu", (unsigned long)rfidID);

    String timestamp = getTimestamp();

    Serial.println("\n🃏 Cartão detectado!");
    Serial.println("   RFID decimal: " + String(rfidDec));
    Serial.println("   Hora (WAT):   " + timestamp);

    enviarPresenca(String(rfidDec), timestamp);

    // Reset
    cardData = 0;
    bitCount = 0;
    delay(1000); // anti-bounce: 1 segundo
  }

  // ── Verificar WiFi e reenviar buffer (cada 30s) ─
  static unsigned long ultimoCheck = 0;
  if (millis() - ultimoCheck > 30000) {
    ultimoCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("⚠️  WiFi perdido. A reconectar...");
      WiFi.reconnect();
      delay(3000);
    } else if (bufferCount > 0) {
      reenviarBuffer();
    }
  }

  delay(10);
}
