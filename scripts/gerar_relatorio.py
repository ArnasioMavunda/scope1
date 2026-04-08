#!/usr/bin/env python3
# ============================================================
#  SCOPE — Gerador de PDF  (chamado pelo PHP api/relatorio.php)
#  Uso: python3 gerar_relatorio.py <dados.json> <saida.pdf>
# ============================================================

import sys
import json
import datetime
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.platypus import (SimpleDocTemplate, Table, TableStyle,
                                Paragraph, Spacer, HRFlowable)
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT

# ── Paleta de cores SCOPE ────────────────────────────────────
AZUL_ESCURO = colors.HexColor("#0f3d91")
AZUL_MID    = colors.HexColor("#2563EB")
VERDE       = colors.HexColor("#10B981")
AMARELO     = colors.HexColor("#F59E0B")
VERMELHO    = colors.HexColor("#EF4444")
CINZA_50    = colors.HexColor("#F8FAFC")
CINZA_100   = colors.HexColor("#F1F5F9")
CINZA_200   = colors.HexColor("#E2E8F0")
CINZA_400   = colors.HexColor("#94A3B8")
CINZA_600   = colors.HexColor("#475569")
CINZA_800   = colors.HexColor("#1E293B")
BRANCO      = colors.white

# ── Estilos de texto ─────────────────────────────────────────
def estilos():
    return {
        "titulo_banner": ParagraphStyle("tb",
            fontSize=13, fontName="Helvetica-Bold",
            textColor=BRANCO, alignment=TA_LEFT, leading=16),
        "subtitulo_banner": ParagraphStyle("sb",
            fontSize=9, fontName="Helvetica",
            textColor=colors.HexColor("#bfdbfe"), alignment=TA_LEFT),
        "data_banner": ParagraphStyle("db",
            fontSize=10, fontName="Helvetica-Bold",
            textColor=BRANCO, alignment=TA_RIGHT),
        "sec": ParagraphStyle("sec",
            fontSize=11, fontName="Helvetica-Bold",
            textColor=AZUL_ESCURO, spaceBefore=10, spaceAfter=4),
        "normal": ParagraphStyle("nor",
            fontSize=9, fontName="Helvetica",
            textColor=CINZA_800, leading=12),
        "small": ParagraphStyle("sm",
            fontSize=8, fontName="Helvetica",
            textColor=CINZA_400),
        "footer": ParagraphStyle("ft",
            fontSize=7.5, fontName="Helvetica",
            textColor=CINZA_400, alignment=TA_CENTER),
        "bold_center": ParagraphStyle("bc",
            fontSize=9, fontName="Helvetica-Bold",
            textColor=CINZA_800, alignment=TA_CENTER),
        "verde_center": ParagraphStyle("gc",
            fontSize=10, fontName="Helvetica-Bold",
            textColor=VERDE, alignment=TA_CENTER),
        "amar_center": ParagraphStyle("yc",
            fontSize=10, fontName="Helvetica-Bold",
            textColor=AMARELO, alignment=TA_CENTER),
        "verm_center": ParagraphStyle("rc",
            fontSize=10, fontName="Helvetica-Bold",
            textColor=VERMELHO, alignment=TA_CENTER),
        "azul_center": ParagraphStyle("ac",
            fontSize=10, fontName="Helvetica-Bold",
            textColor=AZUL_MID, alignment=TA_CENTER),
        "small_center": ParagraphStyle("smc",
            fontSize=8, fontName="Helvetica",
            textColor=CINZA_400, alignment=TA_CENTER),
    }

# ── Formatar data PT ─────────────────────────────────────────
MESES_PT = ["Janeiro","Fevereiro","Março","Abril","Maio","Junho",
            "Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]

def fmt_data(d_str):
    """'2026-03-10' → '10 de Março de 2026'"""
    try:
        d = datetime.date.fromisoformat(d_str)
        return f"{d.day} de {MESES_PT[d.month-1]} de {d.year}"
    except:
        return d_str

def fmt_data_curta(d_str):
    """'2026-03-10' → '10/03/2026'"""
    try:
        d = datetime.date.fromisoformat(d_str)
        return f"{d.day:02d}/{d.month:02d}/{d.year}"
    except:
        return d_str

# ── Mini-stat card ───────────────────────────────────────────
def mini_stat(val, lbl, cor, s):
    t = Table([
        [Paragraph(f"<b>{val}</b>",
            ParagraphStyle("sv", fontSize=18, fontName="Helvetica-Bold",
                           textColor=cor, alignment=TA_CENTER))],
        [Paragraph(lbl,
            ParagraphStyle("sl", fontSize=7.5, fontName="Helvetica",
                           textColor=CINZA_400, alignment=TA_CENTER))],
    ], colWidths=[40*mm])
    t.setStyle(TableStyle([
        ("BACKGROUND",    (0,0),(-1,-1), CINZA_100),
        ("TOPPADDING",    (0,0),(-1,-1), 7),
        ("BOTTOMPADDING", (0,0),(-1,-1), 7),
        ("LEFTPADDING",   (0,0),(-1,-1), 2),
        ("RIGHTPADDING",  (0,0),(-1,-1), 2),
        ("BOX",           (0,0),(-1,-1), 0.5, CINZA_200),
    ]))
    return t

# ── Célula de situação ───────────────────────────────────────
def sit_txt(pct):
    if pct is None: return ("—", CINZA_400)
    pct = float(pct)
    if pct >= 85: return ("Regular",  VERDE)
    if pct >= 70: return ("Em risco", AMARELO)
    return ("Crítico", VERMELHO)

# ════════════════════════════════════════════════════════════
#  FUNÇÃO PRINCIPAL
# ════════════════════════════════════════════════════════════
def gerar(dados: dict, caminho_pdf: str):
    s = estilos()
    story = []

    titulo    = dados.get("titulo", "Relatório de Presenças")
    tipo      = dados.get("tipo", "mensal").capitalize()
    periodo   = dados.get("periodo", {})
    turma     = dados.get("turma", {})
    alunos    = dados.get("alunos", [])
    gerado_em = dados.get("gerado_em", datetime.datetime.now().strftime("%d/%m/%Y %H:%M"))

    inicio_str = periodo.get("inicio", "")
    fim_str    = periodo.get("fim", "")

    # ── DOCUMENTO ────────────────────────────────────────────
    doc = SimpleDocTemplate(
        caminho_pdf, pagesize=A4,
        leftMargin=16*mm, rightMargin=16*mm,
        topMargin=16*mm, bottomMargin=14*mm
    )

    # ── BANNER CABEÇALHO ─────────────────────────────────────
    banner = Table([[
        Paragraph("<b>SCOPE</b>", s["titulo_banner"]),
        Table([[Paragraph(titulo, s["titulo_banner"])],
               [Paragraph(f"{fmt_data_curta(inicio_str)} → {fmt_data_curta(fim_str)}",
                          s["subtitulo_banner"])]],
              colWidths=[115*mm]),
        Paragraph(gerado_em, s["data_banner"]),
    ]], colWidths=[28*mm, 115*mm, 32*mm])
    banner.setStyle(TableStyle([
        ("BACKGROUND",    (0,0),(-1,-1), AZUL_ESCURO),
        ("VALIGN",        (0,0),(-1,-1), "MIDDLE"),
        ("LEFTPADDING",   (0,0),(-1,-1), 10),
        ("RIGHTPADDING",  (0,0),(-1,-1), 10),
        ("TOPPADDING",    (0,0),(-1,-1), 11),
        ("BOTTOMPADDING", (0,0),(-1,-1), 11),
    ]))
    story.append(banner)
    story.append(Spacer(1, 3*mm))

    # ── LINHA DE INFO ─────────────────────────────────────────
    nome_turma  = turma.get("nome",       "12ª CFB")
    ano_letivo  = turma.get("ano_letivo", "2025/2026")
    sala        = turma.get("sala",       "—")
    turno       = "Tarde (13h–18h)" if turma.get("turno") == "tarde" else turma.get("turno","—")

    info = Table([[
        Paragraph(f"<b>Turma:</b>  {nome_turma}", s["normal"]),
        Paragraph(f"<b>Ano Letivo:</b>  {ano_letivo}", s["normal"]),
        Paragraph(f"<b>Turno:</b>  {turno}", s["normal"]),
        Paragraph(f"<b>Sala:</b>  {sala}", s["normal"]),
        Paragraph(f"<b>Total alunos:</b>  {len(alunos)}", s["normal"]),
    ]], colWidths=[35*mm, 35*mm, 45*mm, 22*mm, 38*mm])
    info.setStyle(TableStyle([
        ("BACKGROUND",    (0,0),(-1,-1), CINZA_100),
        ("LEFTPADDING",   (0,0),(-1,-1), 8),
        ("TOPPADDING",    (0,0),(-1,-1), 7),
        ("BOTTOMPADDING", (0,0),(-1,-1), 7),
        ("BOX",           (0,0),(-1,-1), 0.5, CINZA_200),
    ]))
    story.append(info)
    story.append(Spacer(1, 5*mm))

    # ── RESUMO GERAL ──────────────────────────────────────────
    story.append(Paragraph("Resumo Geral", s["sec"]))
    story.append(HRFlowable(width="100%", thickness=1.5, color=AZUL_MID, spaceAfter=4*mm))

    def safe_int(v):
        try: return int(v or 0)
        except: return 0

    def safe_float(v):
        try: return float(v or 0)
        except: return 0.0

    tot_p   = sum(safe_int(a.get("presencas",0))  for a in alunos)
    tot_atr = sum(safe_int(a.get("atrasos",0))     for a in alunos)
    tot_aus = sum(safe_int(a.get("ausencias",0))   for a in alunos)
    tot_fd  = sum(safe_int(a.get("faltas_disc",0)) for a in alunos)
    tot_t   = sum(safe_int(a.get("total",0))       for a in alunos)
    taxa_m  = round((tot_p + tot_atr) / tot_t * 100, 1) if tot_t else 0.0

    stats_row = Table([[
        mini_stat(tot_p,         "Presenças",        VERDE,    s),
        mini_stat(tot_atr,       "Atrasos",          AMARELO,  s),
        mini_stat(tot_aus,       "Ausências",        VERMELHO, s),
        mini_stat(tot_fd,        "F. Disciplinares", colors.HexColor("#8B5CF6"), s),
        mini_stat(f"{taxa_m}%",  "Taxa Média",       AZUL_MID, s),
    ]], colWidths=[43*mm, 43*mm, 43*mm, 43*mm, 43*mm],
        hAlign="LEFT")
    stats_row.setStyle(TableStyle([
        ("LEFTPADDING",  (0,0),(-1,-1), 0),
        ("RIGHTPADDING", (0,0),(-1,-1), 3),
        ("TOPPADDING",   (0,0),(-1,-1), 0),
        ("BOTTOMPADDING",(0,0),(-1,-1), 0),
    ]))
    story.append(stats_row)
    story.append(Spacer(1, 6*mm))

    # ── TABELA DETALHADA POR ALUNO ────────────────────────────
    story.append(Paragraph("Detalhes por Aluno", s["sec"]))
    story.append(HRFlowable(width="100%", thickness=1.5, color=AZUL_MID, spaceAfter=4*mm))

    cabecalho = [
        Paragraph("Nº",       s["bold_center"]),
        Paragraph("Aluno",    ParagraphStyle("ch",fontSize=8,fontName="Helvetica-Bold",
                                             textColor=CINZA_800)),
        Paragraph("Proc.",    s["bold_center"]),
        Paragraph("Pres.",    s["bold_center"]),
        Paragraph("Atraso",   s["bold_center"]),
        Paragraph("Aus.",     s["bold_center"]),
        Paragraph("F.Disc",   s["bold_center"]),
        Paragraph("Total",    s["bold_center"]),
        Paragraph("Taxa %",   s["bold_center"]),
        Paragraph("Situação", s["bold_center"]),
    ]

    rows = [cabecalho]
    for i, a in enumerate(alunos):
        p_   = safe_int(a.get("presencas",0))
        atr_ = safe_int(a.get("atrasos",0))
        aus_ = safe_int(a.get("ausencias",0))
        fd_  = safe_int(a.get("faltas_disc",0))
        tot_ = safe_int(a.get("total",0))
        tx_  = safe_float(a.get("taxa",0))
        sit, cor_sit = sit_txt(tx_)

        rows.append([
            Paragraph(str(i+1), s["small_center"]),
            Paragraph(a.get("nome","—"),
                      ParagraphStyle("nn", fontSize=8, fontName="Helvetica",
                                     textColor=CINZA_800, leading=10)),
            Paragraph(str(a.get("num_processo","—")), s["small_center"]),
            Paragraph(str(p_),   s["verde_center"]),
            Paragraph(str(atr_), s["amar_center"]),
            Paragraph(str(aus_),
                      ParagraphStyle("avc", fontSize=10, fontName="Helvetica-Bold",
                                     textColor=VERMELHO if aus_>3 else CINZA_800,
                                     alignment=TA_CENTER)),
            Paragraph(str(fd_),
                      ParagraphStyle("fdc", fontSize=9, fontName="Helvetica-Bold",
                                     textColor=colors.HexColor("#8B5CF6") if fd_>0 else CINZA_400,
                                     alignment=TA_CENTER)),
            Paragraph(str(tot_), s["small_center"]),
            Paragraph(f"{tx_}%",
                      ParagraphStyle("txc", fontSize=10, fontName="Helvetica-Bold",
                                     textColor=cor_sit, alignment=TA_CENTER)),
            Paragraph(f"<b>{sit}</b>",
                      ParagraphStyle("sc2", fontSize=8, fontName="Helvetica-Bold",
                                     textColor=cor_sit, alignment=TA_CENTER)),
        ])

    col_w = [8*mm, 57*mm, 12*mm, 11*mm, 13*mm, 10*mm, 12*mm, 11*mm, 14*mm, 17*mm]
    tabela = Table(rows, colWidths=col_w, repeatRows=1)
    tabela.setStyle(TableStyle([
        # Cabeçalho
        ("BACKGROUND",    (0,0),(-1,0), AZUL_ESCURO),
        ("TEXTCOLOR",     (0,0),(-1,0), BRANCO),
        ("FONTNAME",      (0,0),(-1,0), "Helvetica-Bold"),
        ("FONTSIZE",      (0,0),(-1,0), 8),
        ("ALIGN",         (0,0),(-1,0), "CENTER"),
        ("TOPPADDING",    (0,0),(-1,0), 7),
        ("BOTTOMPADDING", (0,0),(-1,0), 7),
        # Dados
        ("TOPPADDING",    (0,1),(-1,-1), 5),
        ("BOTTOMPADDING", (0,1),(-1,-1), 5),
        ("LEFTPADDING",   (0,0),(-1,-1), 3),
        ("RIGHTPADDING",  (0,0),(-1,-1), 3),
        ("VALIGN",        (0,0),(-1,-1), "MIDDLE"),
        # Zebra
        *[("BACKGROUND",  (0,i),(-1,i), CINZA_100) for i in range(2, len(rows), 2)],
        # Linhas
        ("LINEBELOW",     (0,0),(-1,0),  1.5, AZUL_MID),
        ("LINEBELOW",     (0,1),(-1,-1), 0.3, CINZA_200),
        ("BOX",           (0,0),(-1,-1), 0.5, CINZA_200),
    ]))
    story.append(tabela)
    story.append(Spacer(1, 7*mm))

    # ── ALUNOS EM RISCO ───────────────────────────────────────
    em_risco = [a for a in alunos
                if safe_int(a.get("total",0)) > 0 and
                   safe_int(a.get("ausencias",0)) / safe_int(a.get("total",1)) > 0.20]

    if em_risco:
        story.append(Paragraph("Alunos em Situação de Risco (>20% ausências)", s["sec"]))
        story.append(HRFlowable(width="100%", thickness=1.5, color=VERMELHO, spaceAfter=4*mm))

        risco_cab = [
            Paragraph("Aluno",           ParagraphStyle("rch",fontSize=8,fontName="Helvetica-Bold",textColor=BRANCO)),
            Paragraph("Ausências",       ParagraphStyle("rch",fontSize=8,fontName="Helvetica-Bold",textColor=BRANCO,alignment=TA_CENTER)),
            Paragraph("% Faltas",        ParagraphStyle("rch",fontSize=8,fontName="Helvetica-Bold",textColor=BRANCO,alignment=TA_CENTER)),
            Paragraph("Limite (1/3)",    ParagraphStyle("rch",fontSize=8,fontName="Helvetica-Bold",textColor=BRANCO,alignment=TA_CENTER)),
            Paragraph("Acção Recomendada", ParagraphStyle("rch",fontSize=8,fontName="Helvetica-Bold",textColor=BRANCO)),
        ]
        risco_rows = [risco_cab]

        for a in em_risco:
            aus_ = safe_int(a.get("ausencias",0))
            tot_ = safe_int(a.get("total",1))
            pf   = round(aus_/tot_*100, 1)
            lim  = tot_//3
            acao = "Participação Disciplinar" if pf >= 33 else "Contactar Encarregado"
            cor_acao = VERMELHO if pf >= 33 else AMARELO

            risco_rows.append([
                Paragraph(a.get("nome","—"),
                          ParagraphStyle("rn",fontSize=8,fontName="Helvetica",textColor=CINZA_800,leading=10)),
                Paragraph(str(aus_),
                          ParagraphStyle("rv",fontSize=10,fontName="Helvetica-Bold",textColor=VERMELHO,alignment=TA_CENTER)),
                Paragraph(f"{pf}%",
                          ParagraphStyle("rv",fontSize=9,fontName="Helvetica-Bold",textColor=VERMELHO,alignment=TA_CENTER)),
                Paragraph(str(lim),
                          ParagraphStyle("rv",fontSize=9,fontName="Helvetica",textColor=CINZA_600,alignment=TA_CENTER)),
                Paragraph(f"<b>{acao}</b>",
                          ParagraphStyle("rv",fontSize=8,fontName="Helvetica-Bold",textColor=cor_acao)),
            ])

        t_risco = Table(risco_rows, colWidths=[67*mm, 22*mm, 20*mm, 22*mm, 44*mm], repeatRows=1)
        t_risco.setStyle(TableStyle([
            ("BACKGROUND",    (0,0),(-1,0), colors.HexColor("#991b1b")),
            ("TEXTCOLOR",     (0,0),(-1,0), BRANCO),
            ("FONTNAME",      (0,0),(-1,0), "Helvetica-Bold"),
            ("FONTSIZE",      (0,0),(-1,0), 8),
            ("TOPPADDING",    (0,0),(-1,0), 7),
            ("BOTTOMPADDING", (0,0),(-1,0), 7),
            ("BACKGROUND",    (0,1),(-1,-1), colors.HexColor("#fff5f5")),
            ("TOPPADDING",    (0,1),(-1,-1), 5),
            ("BOTTOMPADDING", (0,1),(-1,-1), 5),
            ("LEFTPADDING",   (0,0),(-1,-1), 5),
            ("RIGHTPADDING",  (0,0),(-1,-1), 5),
            ("VALIGN",        (0,0),(-1,-1), "MIDDLE"),
            ("LINEBELOW",     (0,0),(-1,-1), 0.3, colors.HexColor("#fca5a5")),
            ("BOX",           (0,0),(-1,-1), 0.8, colors.HexColor("#fca5a5")),
        ]))
        story.append(t_risco)
        story.append(Spacer(1, 5*mm))

    # ── NOTA DE RODAPÉ ────────────────────────────────────────
    story.append(HRFlowable(width="100%", thickness=0.5, color=CINZA_200, spaceAfter=3*mm))
    story.append(Paragraph(
        f"Documento gerado automaticamente pelo sistema SCOPE  ·  {gerado_em}  ·  "
        f"Colégio de Ensino Privado Politécnico de Huambo — www.cepph.com",
        s["footer"]))

    # ── CONSTRUIR PDF ─────────────────────────────────────────
    doc.build(story)
    return True


# ════════════════════════════════════════════════════════════
#  ENTRY POINT
# ════════════════════════════════════════════════════════════
if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Uso: python3 gerar_relatorio.py <dados.json> <saida.pdf>")
        sys.exit(1)

    json_path = sys.argv[1]
    pdf_path  = sys.argv[2]

    try:
        with open(json_path, "r", encoding="utf-8") as f:
            dados = json.load(f)
        gerar(dados, pdf_path)
        print(f"PDF gerado: {pdf_path}")
        sys.exit(0)
    except Exception as e:
        print(f"ERRO: {e}", file=sys.stderr)
        sys.exit(1)
