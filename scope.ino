// =============================================
// ESP32 - SCOPE - Controlo de Presença v4.2
// IP corrigido: 192.168.0.58
// NTP com fallback + diagnóstico Serial
// =============================================

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <time.h>

// =================== CONFIGURAÇÃO ===================
const char* ssid       = "SCOPE";
const char* password   = "scope2027";
const char* serverUrl  = "http://192.168.0.56/scope/api/presenca.php";

// Pinos Wiegand
#define PIN_D0        18
#define PIN_D1        19
#define LED_VERDE      2
#define LED_VERMELHO   4

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

// ── Obter timestamp ───────────────────────────────
String getTimestamp() {
  time_t now;
  struct tm timeinfo;
  time(&now);
  localtime_r(&now, &timeinfo);

  // Se o NTP ainda não sincronizou, ano fica em 1970
  if (timeinfo.tm_year < 100) {
    // Fallback: usar hora local do sistema ou hora fixa de teste
    Serial.println("⚠️  NTP não sincronizado! A usar hora do sistema.");
    // Tenta sincronizar de novo com servidores alternativos
    configTime(3600, 0, "pool.ntp.org", "time.google.com", "time.cloudflare.com");
    delay(2000);
    time(&now);
    localtime_r(&now, &timeinfo);
  }

  char ts[20];
  strftime(ts, sizeof(ts), "%Y-%m-%d %H:%M:%S", &timeinfo);
  return String(ts);
}

// ── Enviar para API ───────────────────────────────
void enviarPresenca(String rfidDecimal, String timestamp) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("❌ WiFi desligado! A reconectar...");
    WiFi.reconnect();
    delay(3000);
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("❌ Falhou reconexão. Cartão ignorado.");
      piscar(LED_VERMELHO, 3, 150);
      return;
    }
  }

  Serial.println("─────────────────────────────────");
  Serial.println("📡 A enviar para: " + String(serverUrl));
  Serial.println("   RFID:      " + rfidDecimal);
  Serial.println("   Timestamp: " + timestamp);

  HTTPClient http;
  http.begin(serverUrl);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(8000);  // 8 segundos de timeout

  String json = "{\"rfid\":\"" + rfidDecimal + "\",\"timestamp\":\"" + timestamp + "\"}";
  int code = http.POST(json);

  if (code > 0) {
    String resposta = http.getString();
    Serial.println("   HTTP " + String(code) + " → " + resposta);

    if (code == 200) {
      // Extrair estado da resposta JSON
      if (resposta.indexOf("presente") >= 0) {
        Serial.println("✅ PRESENTE");
        piscar(LED_VERDE, 1, 600);
      } else if (resposta.indexOf("atraso") >= 0) {
        Serial.println("⏱  ATRASO");
        piscar(LED_VERDE, 2, 300);
      } else if (resposta.indexOf("ausente") >= 0) {
        Serial.println("❌ AUSENTE (fora de prazo)");
        piscar(LED_VERMELHO, 1, 600);
      } else if (resposta.indexOf("saida") >= 0) {
        Serial.println("🚪 SAÍDA registada");
        piscar(LED_VERDE, 3, 200);
      } else if (resposta.indexOf("aviso") >= 0) {
        Serial.println("⚠️  AVISO: " + resposta);
        piscar(LED_VERMELHO, 2, 200);
      } else {
        piscar(LED_VERDE, 1, 400);
      }
    } else {
      Serial.println("❌ Erro HTTP: " + String(code));
      Serial.println("   Resposta: " + resposta);
      piscar(LED_VERMELHO, 2, 300);
    }
  } else {
    Serial.println("❌ Sem resposta do servidor (código: " + String(code) + ")");
    Serial.println("   Verificar: servidor ligado? IP correcto? XAMPP a correr?");
    piscar(LED_VERMELHO, 3, 200);
  }

  http.end();
  Serial.println("─────────────────────────────────");
}

// ====================================================
void setup() {
  Serial.begin(115200);
  delay(500);

  pinMode(LED_VERDE,    OUTPUT);
  pinMode(LED_VERMELHO, OUTPUT);
  digitalWrite(LED_VERDE,    LOW);
  digitalWrite(LED_VERMELHO, LOW);

  // Sinal de arranque
  piscar(LED_VERDE, 2, 100);

  // ── WiFi ───────────────────────────────────────
  Serial.println("\n=== SCOPE ESP32 v4.2 ===");
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
    Serial.println("   Servidor:    " + String(serverUrl));
    piscar(LED_VERDE, 3, 100);
  } else {
    Serial.println("\n❌ FALHOU WiFi! Verificar SSID e senha.");
    piscar(LED_VERMELHO, 5, 200);
    // Continua mesmo sem WiFi para poder ler cartões
  }

  // ── NTP ────────────────────────────────────────
  Serial.println("A sincronizar hora (NTP)...");
  configTime(3600, 0, "pool.ntp.org", "time.google.com", "time.cloudflare.com");

  // Esperar até 10 segundos pelo NTP
  int ntpEspera = 0;
  struct tm timeinfo;
  while (!getLocalTime(&timeinfo) && ntpEspera < 20) {
    delay(500);
    Serial.print(".");
    ntpEspera++;
  }

  if (timeinfo.tm_year > 100) {
    char ts[20];
    strftime(ts, sizeof(ts), "%Y-%m-%d %H:%M:%S", &timeinfo);
    Serial.println("\n✅ Hora sincronizada: " + String(ts));
  } else {
    Serial.println("\n⚠️  NTP falhou. Timestamps podem estar errados.");
  }

  // ── RFID ───────────────────────────────────────
  pinMode(PIN_D0, INPUT_PULLUP);
  pinMode(PIN_D1, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(PIN_D0), handleD0, FALLING);
  attachInterrupt(digitalPinToInterrupt(PIN_D1), handleD1, FALLING);

  Serial.println("\n=== PRONTO — Aguarda cartão ===\n");
}

void loop() {
  // Cartão lido quando receber 26 bits e passar 100ms sem novo bit
  if (bitCount == 26 && (millis() - lastBitTime > 100)) {

    // Extrair 24 bits de dados (remove bits de paridade)
    uint32_t rfidID = (cardData >> 1) & 0xFFFFFF;

    char rfidDec[12];
    sprintf(rfidDec, "%lu", (unsigned long)rfidID);

    String timestamp = getTimestamp();

    Serial.println("\n🃏 Cartão detectado!");
    Serial.println("   Bits recebidos: " + String(bitCount));
    Serial.println("   RFID decimal:   " + String(rfidDec));
    Serial.println("   Hora:           " + timestamp);

    enviarPresenca(String(rfidDec), timestamp);

    // Reset para próxima leitura
    cardData = 0;
    bitCount = 0;
    delay(1000);  // anti-bounce: ignorar leituras duplicadas por 1 segundo
  }

  // Verificar WiFi periodicamente (a cada ~30s)
  static unsigned long ultimoCheck = 0;
  if (millis() - ultimoCheck > 30000) {
    ultimoCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("⚠️  WiFi perdido. A reconectar...");
      WiFi.reconnect();
    }
  }

  delay(10);
}
