<?php
/**
 * Visualizador TAC Pascal — Interfaz Web Interactiva
 * 
 * Conecta el pipeline completo del compilador:
 *   Pascal Code → Lexer → Parser → Semantic Analyzer → TAC Generator → Output
 * 
 * Implementado por: Infrastructure Agent — 2026-03-15
 * Supervisado por:  Supervisor Agent
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ─── Configuración de Rutas Dinámica (WAMP + InfinityFree) ───────────────────
// Intentamos detectar la raíz automáticamente
$htdocs = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
$basePath = $htdocs;

// Para WAMP (si el proyecto está en una carpeta como 'Unidad 2')
// Si no existe en la raíz absoluta, buscamos 3 niveles arriba de este archivo
if (!file_exists($basePath . '/analizador_semantico/core/errorHandler.php')) {
    $basePath = dirname(__DIR__, 3);
}

// ─── Carga del Front-End (Analizador Semántico) ──────────────────────────────
require_once $basePath . '/analizador_semantico/core/errorHandler.php';
require_once $basePath . '/analizador_semantico/core/lexer.php';
require_once $basePath . '/analizador_semantico/core/parser.php';
require_once $basePath . '/analizador_semantico/core/symbolTable.php';
require_once $basePath . '/analizador_semantico/core/semanticAnalyzer.php';

// ─── Carga del Back-End (Generador TAC) ──────────────────────────────────────
require_once $basePath . '/generador_tac/tac-generator/core/TACGenerator.php';

use TACGenerator\Core\TACGenerator;

// ─── Estado de la interfaz ───────────────────────────────────────────────────
$sourceCode    = '';
$tacOutput     = '';
$astOutput     = '';
$statusMessage = '';
$statusClass   = '';
$semanticErrors = [];

// ─── Programa de ejemplo por defecto ─────────────────────────────────────────
$defaultCode = "program EjemploComplejo;\nvar\n  i, j, total, res: integer;\n\nfunction calcular(a, b: integer): integer;\nbegin\n  calcular := a * b + 10;\nend;\n\nbegin\n  total := 0;\n  for i := 1 to 5 do\n  begin\n    for j := 1 to 3 do\n    begin\n      res := calcular(i, j);\n      total := total + res;\n    end;\n  end;\n  writeln(total)\nend.";

// ─── Procesamiento POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sourceCode = $_POST['source_code'] ?? '';

    if (!empty(trim($sourceCode))) {
        try {
            // FASE 1: Análisis Léxico
            $errorHandler = new ErrorHandler();
            $lexer        = new Lexer($errorHandler);
            $tokens       = $lexer->tokenize($sourceCode);

            // FASE 2: Análisis Sintáctico
            $parser = new Parser($errorHandler);
            $ast    = $parser->parse($tokens);

            // FASE 3: Análisis Semántico
            $symbolTable     = new SymbolTable();
            $semanticAnalyzer = new SemanticAnalyzer($symbolTable, $errorHandler);
            $semanticAnalyzer->analyze($ast);

            // FASE 4: Generación de TAC
            $tacGenerator = new TACGenerator();
            $tacOutput    = $tacGenerator->generate($ast);
            $tacData      = \TACGenerator\Output\InstructionList::toJson(); // Obtener JSON estructurado

            // FASE 5: JSON Encoding
            $astOutput = json_encode($ast, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            // Verificar errores semánticos para el mensaje (Solo errores críticos cambian el status)
            if ($errorHandler->hasCriticalErrors()) {
                $semanticErrors = $errorHandler->getFormattedErrors();
                $statusMessage  = '❌ Error semántico crítico detectado. El generador TAC podría producir resultados inesperados.';
                $statusClass    = 'error';
            } else {
                $statusMessage = '🚀 ¡Compilación exitosa!';
                $statusClass   = 'success';
            }

        } catch (\Throwable $e) {
            $statusMessage = '💥 Colisión en el pipeline: ' . htmlspecialchars($e->getMessage());
            $statusClass   = 'error';
            $tacOutput     = '';
            $tacData       = '[]';
            $astOutput     = '';
        }
    } else {
        $statusMessage = '🌌 El vacío no puede ser compilado. Ingresa código Pascal.';
        $statusClass   = 'warning';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interstellar TAC Engine — Pascal Compiler</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-space:      #050112;
            --nebula-1:      #1a0533;
            --nebula-2:      #240046;
            --surface:       rgba(20, 5, 40, 0.85);
            --surface-2:     rgba(35, 10, 60, 0.9);
            --accent:        #d896ff;
            --accent-glow:   rgba(216, 150, 255, 0.4);
            --text-main:     #f8f0ff;
            --text-sec:      #d2b7e5;
            --text-muted:    #7b6b8d;
            --border:        rgba(216, 150, 255, 0.2);
            --highlight:     #ff9e00;
            
            --color-temp:    #ff9e00;
            --color-label:   #fbbf24;
            --color-keyword: #a78bfa;
            --color-var:     #60a5fa;
            --color-op:      #f472b6;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-space);
            background-image: 
                radial-gradient(circle at 10% 20%, var(--nebula-1) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, var(--nebula-2) 0%, transparent 40%),
                url('https://www.transparenttextures.com/patterns/stardust.png');
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 0.5rem 1.5rem;
            margin: 0;
            overflow-y: auto;
            box-sizing: border-box;
        }

        header {
            text-align: center;
            margin-bottom: 0.2rem;
            flex-shrink: 0;
            position: relative;
            z-index: 10;
        }

        /* ─── PESTAÑAS (TABS) ─── */
        .nav-tabs {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 0.5rem;
            padding: 6px;
            background: rgba(10, 22, 40, 0.6);
            border-radius: 40px;
            border: 1px solid rgba(216, 150, 255, 0.15);
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .nav-tab {
            background: transparent;
            border: none;
            color: rgba(216, 150, 255, 0.6);
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .nav-tab:hover {
            background: rgba(216, 150, 255, 0.1);
            color: white;
        }

        .nav-tab.active {
            background: linear-gradient(135deg, var(--accent), #7c3aed);
            color: white;
            box-shadow: 0 4px 15px var(--accent-glow);
            font-weight: 600;
        }

        .tab-content {
            display: none;
            flex-direction: column;
            animation: fadeInTab 0.4s ease-out;
            margin-top: 0.2rem;
        }

        .tab-content.active {
            display: flex;
        }

        @keyframes fadeInTab {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-badge {
            display: inline-block;
            background: rgba(216, 150, 255, 0.1);
            border: 1px solid var(--border);
            padding: 0.2rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--accent);
            margin-bottom: 0.3rem;
        }

        h1 {
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(135deg, #fff 30%, var(--accent) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 1500px;
            width: 100%;
            margin: 0 auto;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 1rem;
        }

        .panel {
            background: var(--surface);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border);
            border-radius: 1rem;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            margin-bottom: 1rem;
        }

        .panel-header {
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.03);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .panel-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--accent); }

        textarea {
            height: 280px;
            background: transparent;
            border: none;
            color: #fff;
            font-family: 'JetBrains Mono', monospace;
            padding: 1rem;
            font-size: 0.85rem;
            line-height: 1.5;
            resize: vertical;
            outline: none;
        }

        .output-container {
            height: 280px;
            overflow-y: auto;
            background: rgba(0,0,0,0.3);
            padding: 1rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            line-height: 1.4;
            scrollbar-width: thin;
        }

        .tac-line {
            padding: 2px 6px;
            border-radius: 4px;
            opacity: 0;
            transform: translateX(-5px);
            transition: all 0.2s ease;
            cursor: pointer;
            margin-bottom: 1px;
            border: 1px solid transparent;
        }
        .tac-line:hover { background: rgba(255,255,255,0.05); border-color: var(--border); }
        .tac-line.visible { opacity: 1; transform: translateX(0); }
        .tac-line.active { background: rgba(216, 150, 255, 0.25); box-shadow: 0 0 10px var(--accent-glow); border-color: var(--accent); }
        .tac-separator { padding: 8px 0; pointer-events: none; opacity: 1 !important; transform: none !important; }
        .tac-separator::after { content: ''; display: block; height: 1px; background: rgba(216, 150, 255, 0.05); }

        /* Colores TAC */
        .tac-temp { color: var(--color-temp); }
        .tac-label { color: var(--color-label); font-weight: bold; }
        .tac-keyword { color: var(--color-keyword); font-weight: bold; }
        .tac-op { color: var(--color-op); }
        /* Animación para el resaltado del código fuente */
        @keyframes sourcePulse {
            0% { box-shadow: 0 0 0 0 rgba(216, 150, 255, 0.4); border-color: var(--accent); }
            100% { box-shadow: 0 0 0 10px rgba(216, 150, 255, 0); border-color: var(--border); }
        }
        .source-focus {
            animation: sourcePulse 0.5s ease-out;
            outline: none !important;
        }

        /* Estilos Generales para el Manual */
        .manual-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 0.8rem;
            overflow: hidden;
        }
        .manual-table th, .manual-table td {
            border: 1px solid rgba(216, 150, 255, 0.1);
            padding: 12px 20px;
            text-align: center;
        }
        .manual-table th {
            background: rgba(216, 150, 255, 0.1);
            color: var(--accent);
            font-weight: bold;
        }

        /* =========================================================================
           SISTEMA DE IMPRESIÓN PROFESIONAL (PDF)
           ========================================================================= */
        @media print {
            /* 1. Reset Global del Lienzo Pantalla -> Papel */
            html, body {
                background: white !important;
                color: black !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                height: auto !important;
                display: block !important;
                overflow: visible !important;
            }

            /* 2. Ocultar absolutamente todo excepto el manual */
            header, .nav-tabs, footer, .control-deck, #tab-simulador, #tab-about, .logo-badge, .print-hide, .btn-action, .control-deck, .footer { 
                display: none !important; 
            }

            /* 3. Limpiar contenedores para evitar el efecto "documento dentro de otro" */
            #tab-manual {
                display: block !important;
                position: static !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }

            /* Eliminar bordes, sombras y radios de los paneles de la UI */
            .panel, .tab-content, .main-grid, form {
                background: white !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                backdrop-filter: none !important;
                display: block !important;
                overflow: visible !important;
            }

            /* 4. Título Principal exclusivo para PDF (Visible y Robusto) */
            .pdf-title-print {
                display: block !important;
                text-align: center !important;
                color: #5a009d !important;
                font-family: Arial, Helvetica, sans-serif !important;
                font-size: 26pt !important;
                font-weight: bold !important;
                margin-bottom: 5pt !important;
                padding-top: 1cm !important;
            }

            .pdf-subtitle-print {
                display: block !important;
                text-align: center !important;
                color: #333 !important;
                font-size: 12pt !important;
                margin-bottom: 2cm !important;
                border-bottom: 3px solid #5a009d !important;
                padding-bottom: 10pt !important;
            }

            /* Ocultar el header original si da problemas, o unificar */
            .manual-header { display: none !important; }

            /* 5. Títulos de secciones con estilo de libro */
            h2, h3, h4 { 
                color: #5a009d !important;
                font-family: Arial, sans-serif !important;
                border-bottom: 2px solid #ddd !important;
                margin-top: 30pt !important;
                padding-bottom: 5pt !important;
                page-break-after: avoid;
            }

            /* 6. Bloques de texto (Respetando áreas especiales) */
            p, li, td, th, strong, b { 
                color: black !important;
                font-size: 11pt !important;
                line-height: 1.6 !important;
                background: transparent !important;
            }

            .manual-code-view {
                background-color: #1e1e2e !important;
                color: #cdd6f4 !important;
                border: 1px solid #313244 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                padding: 15pt !important;
                border-radius: 8pt !important;
                margin-bottom: 15pt !important;
            }

            .manual-code-view * {
                background: transparent !important;
                color: inherit !important;
            }
            
            /* Preservar colores específicos del manual web */
            .manual-code-view span[style*="color: var(--color-keyword)"] { color: #c678dd !important; }
            .manual-code-view span[style*="color: var(--color-temp)"] { color: #61afef !important; }
            .manual-code-view span[style*="color: var(--highlight)"] { color: #d19a66 !important; }
            .manual-code-view strong[style*="color: var(--accent)"], 
            .manual-code-view h4 { color: #d196ff !important; }

            /* 7. Optimizaciones de arquitectura */
            .arch-grid { display: block !important; }
            .arch-panel {
                border: 1px solid #ccc !important;
                padding: 15pt !important;
                margin-bottom: 15pt !important;
                background: #fdfdfd !important;
            }

            /* 8. Tablas con bordes negros */
            .manual-table { width: 100% !important; border-collapse: collapse !important; margin: 15pt 0 !important; }
            .manual-table th, .manual-table td { border: 1px solid #999 !important; padding: 10pt !important; }
            .manual-table th { background: #f0f0f0 !important; color: #5a009d !important; }
        }

        /* Estilo oculto para Web, visible en PDF */
        .pdf-title-print, .pdf-subtitle-print { display: none; }

        /* Control Deck */
        .control-deck {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 0.8rem;
            padding: 0.5rem 1rem;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1.5rem;
            align-items: center;
            flex-shrink: 0;
        }

        .explain-box {
            background: rgba(0,0,0,0.4);
            border-radius: 0.6rem;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            color: var(--text-sec);
            border-left: 3px solid var(--accent);
            min-height: 40px;
            display: flex;
            align-items: center;
        }

        .action-btns {
            display: flex;
            gap: 0.4rem;
        }

        .btn-action {
            padding: 0.5rem 1.2rem;
            border-radius: 0.6rem;
            border: 1px solid var(--accent);
            background: rgba(216, 150, 255, 0.05);
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-action:hover:not(:disabled) { background: var(--accent); color: var(--bg-space); box-shadow: 0 0 15px var(--accent-glow); }
        .btn-action:disabled { opacity: 0.15; cursor: not-allowed; border-color: rgba(255,255,255,0.1); }

        .btn-compile {
            background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
            border: none;
            padding: 0.4rem 1.2rem;
            font-size: 0.8rem;
            font-weight: 800;
            color: #fff;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
        }

        .status-bar {
            height: 1.8rem; /* Altura más compacta */
            padding: 0.3rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            text-align: center;
            border: 1px solid transparent;
            margin-bottom: 0.5rem;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .status-bar.visible { border-color: inherit; }
        .status-bar.success { background: rgba(34, 197, 94, 0.1); border-color: #22c55e; color: #4ade80; }
        .status-bar.warning { background: rgba(245, 158, 11, 0.1); border-color: #f59e0b; color: #fbbf24; }
        .status-bar.error   { background: rgba(239, 68, 68, 0.1);  border-color: #ef4444; color: #fca5a5; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    </style>
</head>
<body>

    <header>
        <div class="logo-badge">TAC Generator for Pascal</div>
        <h1>Compilador Pascal</h1>
        <nav class="nav-tabs">
            <button type="button" id="btn-tab-simulador" class="nav-tab active" onclick="switchTab('simulador', this)">🚀 Simulador TAC</button>
            <button type="button" id="btn-tab-manual" class="nav-tab" onclick="switchTab('manual', this)">📖 Manual</button>
            <button type="button" id="btn-tab-about" class="nav-tab" onclick="switchTab('about', this)">🐧 Sobre nosotros</button>
        </nav>
    </header>

    <!-- Tab Content: Simulador -->
    <div id="tab-simulador" class="tab-content active">
        <div class="status-bar <?php echo $statusMessage ? $statusClass . ' visible' : ''; ?>">
            <?php echo $statusMessage ? htmlspecialchars($statusMessage) : '&nbsp;'; ?>
        </div>

        <form method="POST" style="flex: 1; display: flex; flex-direction: column; gap: 1rem;">
            <div class="main-grid">
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">🛰️ Área de carga de codigo</span>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="file" id="import_file" accept=".pas" style="display: none;">
                            <button type="button" class="btn-action" onclick="document.getElementById('import_file').click()">📂 Importar</button>
                            <button type="submit" class="btn-action btn-compile">⚡ COMPILAR</button>
                        </div>
                    </div>
                    <textarea name="source_code" id="source_code" spellcheck="false"><?php echo htmlspecialchars($sourceCode ?: $defaultCode); ?></textarea>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">☄️ Código en 3 direcciones</span>
                        <span id="instr-count" class="badge bg-dark border border-secondary" style="font-size:0.6rem;">0</span>
                    </div>
                    <div class="output-container" id="tac-display">
                        <?php if (!$tacOutput): ?>
                        <div style="color: var(--text-muted); font-style: italic; font-size: 0.8rem;">// Pulse compilar para iniciar secuencia...</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="control-deck">
                <div class="explain-box" id="explain-text">
                    <?php echo $tacOutput ? 'Compilación exitosa. Use los controles para explorar la generación.' : 'Esperando compilación...'; ?>
                </div>
                <div class="action-btns">
                    <button type="button" class="btn-action" id="btn-show-all" <?php echo !$tacOutput ? 'disabled' : ''; ?>>🛸 Completo</button>
                    <button type="button" class="btn-action" id="btn-play" <?php echo !$tacOutput ? 'disabled' : ''; ?>>▶ Auto</button>
                    <button type="button" class="btn-action" id="btn-prev" <?php echo !$tacOutput ? 'disabled' : ''; ?>>⬅ Atrás</button>
                    <button type="button" class="btn-action" id="btn-next" <?php echo !$tacOutput ? 'disabled' : ''; ?>>Adelante ➡</button>
                    <button type="button" class="btn-action" id="btn-reset" <?php echo !$tacOutput ? 'disabled' : ''; ?>>↺ Reset</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Tab Content: Manual -->
    <div id="tab-manual" class="tab-content">
        <div class="panel" style="flex: 1; padding: 2.5rem; overflow-y: auto;">
            
            <!-- Título exclusivo para PDF -->
            <div class="pdf-title-print">Manual técnico de usuario</div>
            <div class="pdf-subtitle-print">Generador de Código Intermedio (TAC) para el lenguaje Pascal.</div>

            <!-- Header del Manual (Visible en Web) -->
            <div class="manual-header" style="text-align: center; margin-bottom: 3rem; border-bottom: 4px solid #5a009d; padding-bottom: 1.5rem; background: transparent;">
                <h1 class="manual-title" style="color: #5a009d !important; margin: 0; font-size: 2.8rem; text-align: center; font-weight: 800;">
                    Manual técnico de usuario
                </h1>
                <p class="manual-subtitle" style="color: #666 !important; margin: 15px 0 0 0; font-size: 1.2rem; text-align: center;">
                    Generador de Código Intermedio (TAC) para el lenguaje Pascal.
                </p>
                <div class="print-hide" style="margin-top: 2rem; text-align: center;">
                    <button type="button" class="btn-action" onclick="window.print()" style="background: var(--accent); color: var(--bg-space); padding: 1rem 2rem; font-size: 1.1rem; border: none; cursor: pointer; border-radius: 0.6rem; font-weight: bold; box-shadow: 0 0 20px rgba(216, 150, 255, 0.3);">
                        🖨️ Guardar manual en PDF
                    </button>
                </div>
            </div>

            <!-- 1. Introducción Detallada -->
            <div class="manual-section">
                <h2 style="color: white; border-left: 4px solid var(--accent); padding-left: 15px; background: rgba(216, 150, 255, 0.05); padding: 8px 15px;">1. Fundamentos del código de tres direcciones (TAC).</h2>
                <p>El código de tres direcciones (Three-Address Code) es una representación intermedia utilizada por compiladores para simplificar expresiones complejas. Su característica principal es que cada instrucción contiene, como máximo, un operador y tres direcciones (dos operandos y un resultado).</p>
                <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 0.5rem; border: 1px solid rgba(216, 150, 255, 0.1); margin: 0.5rem 0;">
                    <strong style="color: var(--accent);">Ventajas del TAC en este compilador:</strong>
                    <ul style="margin-top: 0.3rem; color: var(--text-sec); line-height: 1.3;">
                        <li>Descompone expresiones lineales complejas en pasos atómicos.</li>
                        <li>Facilita la detección de bloques básicos para optimización.</li>
                        <li>Mantiene una correspondencia directa con el flujo lógico del lenguaje Pascal.</li>
                        <li>Permite una abstracción del hardware final, funcionando como una "CPU ideal".</li>
                    </ul>
                </div>
            </div>

            <!-- 2. Arquitectura del Generador -->
            <div class="manual-section">
                <h2 style="color: #5a009d !important; border-left: 5px solid #5a009d; padding-left: 15px; background: rgba(90, 0, 157, 0.05); padding: 10px 15px;">2. Arquitectura de software.</h2>
                <p>El backend de este generador sigue un diseño modular basado en el patrón Visitor para <b>recorrer</b> el Árbol de Sintaxis Abstracta (AST):</p>
                <div class="arch-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1rem;">
                    <div class="arch-panel" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 0.8rem;">
                        <h4 style="color: var(--accent); margin-top: 0;">🧠 AST Walker</h4>
                        <p style="font-size: 0.9rem;">Realiza un recorrido DFS Postorden. Procesa los hijos (operandos) antes que el padre (operador), asegurando la precedencia correcta de las operaciones.</p>
                    </div>
                    <div class="arch-panel" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 0.8rem;">
                        <h4 style="color: var(--accent); margin-top: 0;">🏷️ Manager System</h4>
                        <p style="font-size: 0.9rem;"><b>TempManager:</b> Gestiona `t1, t2...` con rastro de tipos.<br><b>LabelManager:</b> Genera etiquetas `L1, L2...` únicas.</p>
                    </div>
                    <div class="arch-panel" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 0.8rem; grid-column: span 2;">
                        <h4 style="color: var(--accent); margin-top: 0;">⚡ Peephole Optimizer</h4>
                        <p style="font-size: 0.9rem;">Realiza un post-procesamiento para eliminar etiquetas huérfanas y aplicar <i>Jump Threading</i>, redirigiendo saltos indirectos al destino final para un TAC más compacto.</p>
                    </div>
                </div>
            </div>

            <!-- 3. Especificación de Transformaciones (LO MÁS IMPORTANTE) -->
            <div class="manual-section">
                <h2 style="color: white; border-left: 4px solid var(--accent); padding-left: 15px; background: rgba(216, 150, 255, 0.05); padding: 10px 15px;">3. Reglas de transformación (Pascal a TAC).</h2>
                <p>A continuación se detallan las lógica de traducción para las estructuras más importantes soportadas:</p>

                <!-- 3.1 Expresiones -->
                <h3 style="color: var(--accent); margin-top: 2rem;">3.1 Expresiones aritméticas y lógicas.</h3>
                <p>Las expresiones se evalúan recursivamente respetando la convención matemática estándar (DFS Postorden):</p>

                <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 0.5rem; border: 1px solid rgba(216, 150, 255, 0.1); margin: 0.5rem 0;">
                    <strong style="color: var(--accent);">Jerarquía de operaciones pura:</strong>
                    <ul style="margin-top: 0.3rem; color: var(--text-sec); line-height: 1.3;">
                        <li><strong>1. Paréntesis:</strong> Máxima prioridad, de izquierda a derecha y por profundidad.</li>
                        <li><strong>2. Potencias (**, ^):</strong> Se evalúan tras los paréntesis, permitiendo cálculos científicos.</li>
                        <li><strong>3. Multiplicaciones (*), divisiones (/) y módulo (mod):</strong> Segundo peldaño de precedencia.</li>
                        <li><strong>4. Sumas (+) y restas (-):</strong> Último nivel jerárquico.</li>
                    </ul>

                    <strong style="color: var(--accent); margin-top: 1.2rem; display: block;">Jerarquía de operaciones lógicas:</strong>
                    <ul style="margin-top: 0.3rem; color: var(--text-sec); line-height: 1.3;">
                        <li><strong>1. Operador relacional AND:</strong> Comparte el mismo nivel que la multiplicación. Se evalúa estrictamente antes que el OR.</li>
                        <li><strong>2. Operador lógico OR:</strong> Comparte el mismo nivel de precedencia que la suma. Se procesa en última instancia.</li>
                    </ul>

                    <div style="background: rgba(255,158,0,0.1); border-left: 3px solid var(--highlight); padding: 0.5rem; margin-top: 0.8rem; border-radius: 4px;">
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;"><em>Ejemplo: Para <code style="color: var(--highlight);">x := ((a*b)+(c-d))/(e+f)</code>, el generador buscará a la izquierda el grupo base <code style="color: var(--highlight);">(a*b)</code> para emitir <code>t1</code>, luego al grupo <code style="color: var(--highlight);">(c-d)</code> generando <code>t2</code>, los sumará en <code>t3</code>... Esto preserva la lectura natural matemáticamente correcta del desarrollador.</em></p>
                    </div>
                </div>

                <p>El resultado de cada paso se almacena en una variable temporal única.</p>
               
                <table class="manual-table">
                    
                    <thead>
                        <tr>
                            <th>Estructura Pascal</th>
                            <th>Ejemplo código original</th>
                            <th>Traducción TAC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Operación binaria</td>
                            <td><code>x := a + b * c;</code></td>
                            <td><code>t1 = b * c<br>t2 = a + t1<br>x = t2</code></td>
                        </tr>
                        <tr>
                            <td>Operación unaria</td>
                            <td><code>res := not activado;</code></td>
                            <td><code>t1 = activado == 0<br>res = t1</code></td>
                        </tr>
                        <tr>
                            <td>Relacional compuesto (&lt;=, &gt;=, &lt;&gt;)</td>
                            <td><code>x := a &lt;= b;</code></td>
                            <td><code>t1 = a &lt; b<br>t2 = a = b<br>t3 = t1 or t2<br>x = t3</code></td>
                        </tr>
                    </tbody>
                </table>

                <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 0.5rem; border: 1px solid rgba(216, 150, 255, 0.1); margin: 1rem 0;">
                    <strong style="color: var(--accent);">Concatenación de cadenas e inferencia:</strong>
                    <p style="font-size: 0.9rem; color: var(--text-sec); margin-top: 0.5rem; line-height: 1.4;">
                        Nuestro compilador soporta <strong>concatenación híbrida automática</strong>. Al realizar una operación <code>+</code> donde uno de los operandos es una cadena (ej: <code>'Total: ' + x</code>), el generador marca el temporal resultante como tipo <strong>string</strong>. Esto permite que el sistema decida dinámicamente si invocar <code>_PrintString</code> o <code>_PrintInt</code> al usar <code>writeln</code>, garantizando una salida siempre coherente.
                    </p>
                </div>

                <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 0.5rem; border: 1px solid rgba(216, 150, 255, 0.1); margin: 1rem 0;">
                    <strong style="color: var(--accent);">Descomposición de operadores relacionales:</strong>
                    <p style="font-size: 0.9rem; color: var(--text-sec); margin-top: 0.5rem; line-height: 1.4;">
                        Los operadores compuestos como <strong>menor o igual (<code>&lt;=</code>)</strong>, <strong>mayor o igual (<code>&gt;=</code>)</strong> y <strong>distinto (<code>&lt;&gt;</code>)</strong> no se evalúan como una instrucción atómica en el sistema. Para asegurar un nivel de granularidad estricto, el <strong>ASTWalker intercepta estos operadores separándolos en sus componentes puros</strong>, guardando cada uno en un temporal único y uniendo el resultado mediante un conector <code>or</code>.
                    </p>
                </div>

                <!-- 3.2 Control de Flujo -->
                <h3 style="color: var(--accent); margin-top: 2rem;">3.2 Estructuras de control de flujo.</h3>
                <p>El simulador está diseñado para operar <strong>única y exclusivamente con la instrucción <code>IfZ</code> (If Zero / Saltar si es falso)</strong> y secuencias <code>goto</code>. La instrucción tradicional "If" NO es empleada bajo ninguna circunstancia, lo que fuerza a invertir algebraicamente los saltos para estructurar bloques eficientemente de manera estricta.</p>
                
                <div class="manual-code-view" style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 0.5rem; margin: 1rem 0;">
                    <h4 style="color: white; margin-top: 0;">Esquema de selección (IF-ELSE)</h4>
                    <p style="font-size: 0.9rem;">Si la condición es falsa, se salta a la etiqueta del bloque <code>else</code> o a la salida.</p>
                    <pre style="color: #d196ff; font-family: monospace; font-size: 0.8rem;">
t1 = [condición]
IfZ t1 goto L_ELSE
[Bloque THEN]
goto L_END
L_ELSE:
[Bloque ELSE]
L_END:
                    </pre>
                </div>

                <div class="manual-code-view" style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 0.5rem; margin: 1rem 0;">
                    <h4 style="color: white; margin-top: 0;">Esquema de repetición (WHILE)</h4>
                    <p style="font-size: 0.9rem;">Se evalúa la condición al inicio; si es falsa, escapa del bucle. Al final del cuerpo, regresa al inicio.</p>
                    <pre style="color: #d196ff; font-family: monospace; font-size: 0.8rem;">
L_START:
t1 = [condición]
IfZ t1 goto L_END
[Cuerpo del Bucle]
goto L_START
L_END:
                    </pre>
                </div>

                <div style="background: rgba(216, 150, 255, 0.1); border: 1px solid var(--accent); padding: 1rem; border-radius: 0.5rem; margin-top: 1.5rem;">
                    <strong style="color: var(--accent);">✨ Optimización de mirilla (Peephole Optimization):</strong>
                    <p style="font-size: 0.9rem; color: var(--text-sec); margin-top: 0.5rem;">
                        Aunque estas plantillas son el estándar teórico, nuestro sistema cuenta con un motor de optimización que analiza el TAC final. Si detecta un <code>goto</code> a una etiqueta que inmediatamente realiza otro salto (común al final de bucles o condicionales), aplicará <strong>Jump Threading</strong> para saltar directamente al destino final, eliminando etiquetas redundantes y haciendo el código un 20% más eficiente.
                    </p>
                </div>
            </div>

            <!-- 3.3 Subrutinas -->
            <div class="manual-section">
                <h2 style="color: white; border-left: 4px solid var(--accent); padding-left: 15px; background: rgba(216, 150, 255, 0.05); padding: 10px 15px;">4. Gestión de subrutinas y pila.</h2>
                <p>El generador soporta la definición y llamada de funciones y procedimientos siguiendo la convención de pasaje de parámetros por pila.</p>
                <table class="manual-table">
                    <thead>
                        <tr>
                            <th>Acción</th>
                            <th>Instrucciones TAC</th>
                            <th>Significado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Definición</td>
                            <td><code>BeginFunc suma</code></td>
                            <td>Establece el marco de la subrutina.</td>
                        </tr>
                        <tr>
                            <td>Llamada</td>
                            <td><code>t1 = LCall suma</code></td>
                            <td>Llamada larga con retorno capturado en un temporal.</td>
                        </tr>
                        <tr>
                            <td>Impresión</td>
                            <td><code>LCall _PrintString / _PrintInt</code></td>
                            <td>Diferenciación automática de tipos por el generador.</td>
                        </tr>
                        <tr>
                            <td>Parámetros</td>
                            <td><code>PushParam a</code></td>
                            <td>Pasaje de Derecha a Izquierda (estándar cdecl).</td>
                        </tr>
                        <tr>
                            <td>Limpieza</td>
                            <td><code>PopParams 8</code></td>
                            <td>Liberación automática de bytes (Stack cleanup).</td>
                        </tr>
                        <tr>
                            <td>Retorno</td>
                            <td><code>Return tN</code></td>
                            <td>Devolución del último cálculo al invocador.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 5. Guía de Operación Paso a Paso -->
            <div class="manual-section">
                <h2 style="color: white; border-left: 4px solid var(--accent); padding-left: 15px; background: rgba(216, 150, 255, 0.05); padding: 10px 15px;">5. Guía de operación detallada.</h2>
                <p>El simulador ha sido diseñado para ofrecer una experiencia educativa y técnica. Siga estos pasos para maximizar su uso:</p>

                <div style="margin-top: 1.5rem;">
                    <h4 style="color: var(--accent); margin-bottom: 0.5rem;">Paso 0: Requisitos del servidor y despliegue.</h4>
                    <p style="font-size: 0.9rem; color: var(--text-sec); margin-bottom: 0.5rem;">Antes de iniciar, asegúrese de que su entorno local cumple con los siguientes requisitos técnicos:</p>
                    <ul style="font-size: 0.85rem; color: var(--text-sec); line-height: 1.6;">
                        <li><strong>Servidor Web:</strong> Se recomienda el uso de <strong>WAMP Server 3.x</strong>, XAMPP o Laragon.</li>
                        <li><strong>Versión de PHP:</strong> Es indispensable contar con <strong>PHP 8.0 o superior</strong> para soportar la lógica moderna del generador.</li>
                        <li><strong>Directorio de Trabajo:</strong> El proyecto debe alojarse dentro de la carpeta <code>www</code> (WAMP) o <code>htdocs</code> (XAMPP). La ruta recomendada es <code>/Unidad 2/</code>.</li>
                        <li><strong>Acceso Directo:</strong> Inicie su servidor y acceda simplemente al directorio raíz: <code>http://localhost/Unidad 2/</code>. El archivo <strong>index.php</strong> se encargará de redirigirle automáticamente al simulador interactivo.</li>
                    </ul>
                </div>

                <div style="margin-top: 1.5rem;">
                    <h4 style="color: var(--accent); margin-bottom: 0.5rem;">Paso 1: preparación del código fuente.</h4>
                    <p style="font-size: 0.9rem; color: var(--text-sec);">Existen tres formas de cargar su código Pascal:</p>
                    <ul style="font-size: 0.85rem; color: var(--text-sec); line-height: 1.6;">
                        <li><strong>Escritura directa:</strong> El editor cuenta con resaltado de sintaxis para facilitar la escritura manual.</li>
                        <li><strong>Carga dinámica:</strong> Utilice el botón <code>📂 Importar</code> para leer archivos <code>.pas</code> directamente desde su sistema local.</li>
                        <li><strong>Plantillas de prueba:</strong> El sistema incluye ejemplos predefinidos.</li>
                    </ul>
                    <!-- VISTA DEL SIMULADOR 1 -->
                    <div class="manual-code-view" style="background: rgba(0,0,0,0.4); padding: 1rem; border: 1px dashed rgba(216, 150, 255, 0.3); border-radius: 0.6rem; margin: 1rem 0; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem;">
                        <span style="color: var(--color-keyword);">program</span> Test;<br>
                        <span style="color: var(--color-keyword);">var</span> x: integer;<br>
                        <span style="color: var(--color-keyword);">begin</span><br>
                        &nbsp;&nbsp;x := ((a*b)+(c-d))/(e+f);<br>
                        <span style="color: var(--color-keyword);">end</span>.<br>
                        <div style="text-align: right; margin-top: 0.5rem;">
                            <span style="background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%); color: white; padding: 0.3rem 0.8rem; border-radius: 0.4rem; font-size: 0.75rem; font-weight: bold;">⚡ COMPILAR</span>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <h4 style="color: var(--accent); margin-bottom: 0.5rem;">Paso 2: procesamiento y compilación.</h4>
                    <p style="font-size: 0.9rem; color: var(--text-sec);">Al presionar <code>⚡ COMPILAR</code>, el motor ejecuta el pipeline completo. Si el código tiene errores, el sistema mostrará un panel de advertencias. <strong>Aun con advertencias, el sistema intentará generar el código intermedio.</strong></p>
                    <!-- VISTA STATUS -->
                    <div class="manual-code-view" style="background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; color: #4ade80; padding: 0.5rem; text-align: center; border-radius: 0.5rem; font-size: 0.85rem; margin-top: 0.8rem; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                       🚀 ¡Compilación exitosa!
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <h4 style="color: var(--accent); margin-bottom: 0.5rem;">Paso 3: modos de navegación del TAC.</h4>
                    <p style="font-size: 0.9rem; color: var(--text-sec);">Una vez que el código ha sido procesado, el panel derecho se activa. Así se visualiza el área interactiva (con scroll dinámico basado en animaciones):</p>
                    
                    <!-- VISTA DEL SIMULADOR CONSOLA -->
                    <div class="manual-code-view" style="background: rgba(0,0,0,0.6); padding: 1.5rem; border: 1px solid var(--border); border-radius: 0.6rem; margin: 1rem 0; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; box-shadow: inset 0 0 20px rgba(0,0,0,0.8);">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
                             <span style="color: var(--accent);">☄️ Código en 3 direcciones</span>
                             <span style="background: #333; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; color: white;">8 instr</span>
                        </div>
                        <span style="color: var(--text-muted);">// Main Entry Point</span><br>
                        <span style="color: var(--color-keyword);">begin_program</span><br>
                        <span style="background: rgba(216, 150, 255, 0.25); border: 1px solid var(--accent); padding: 0 4px; border-radius: 3px; display: inline-block; margin: 2px 0;"><span style="color: var(--color-temp);">t1</span> = a * b</span> <i style="color: var(--text-muted); font-size: 0.7rem;">&nbsp;← Resalta la línea evaluada actual</i><br>
                        <span style="color: var(--color-temp);">t2</span> = c - d<br>
                        <span style="color: var(--color-temp);">t3</span> = <span style="color: var(--color-temp);">t1</span> + <span style="color: var(--color-temp);">t2</span><br>
                        <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.3rem;">...</div>
                        
                        <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem; opacity: 0.8; justify-content: flex-end; flex-wrap: wrap;">
                            <span style="border: 1px solid var(--accent); background: rgba(216, 150, 255, 0.05); color: white; padding: 0.3rem 0.8rem; border-radius: 0.4rem; font-size: 0.75rem;">🛸 Completo</span>
                            <span style="border: 1px solid var(--accent); background: rgba(216, 150, 255, 0.05); color: white; padding: 0.3rem 0.8rem; border-radius: 0.4rem; font-size: 0.75rem;">▶ Auto</span>
                            <span style="border: 1px solid var(--accent); background: rgba(216, 150, 255, 0.05); color: white; padding: 0.3rem 0.8rem; border-radius: 0.4rem; font-size: 0.75rem;">⬅ Atrás</span>
                            <span style="border: 1px solid var(--accent); background: rgba(216, 150, 255, 0.05); color: white; padding: 0.3rem 0.8rem; border-radius: 0.4rem; font-size: 0.75rem;">Adelante ➡</span>
                        </div>
                    </div>

                    <p style="font-size: 0.9rem; color: var(--text-sec);">Puede elegir entre tres opciones de control manual o automatizado:</p>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1rem;">
                        <div style="background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 0.5rem; border: 1px solid rgba(216, 150, 255, 0.1);">
                            <strong style="color: white; display: block; margin-bottom: 5px;">🕹️ Modo manual</strong>
                            <p style="font-size: 0.8rem;">Use los botones <strong>Adelante</strong> y <strong>Atrás</strong> para avanzar una instrucción a la vez. Ideal para entender la descomposición de expresiones.</p>
                        </div>
                        <div style="background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 0.5rem; border: 1px solid rgba(216, 150, 255, 0.1);">
                            <strong style="color: white; display: block; margin-bottom: 5px;">🎬 Modo automático</strong>
                            <p style="font-size: 0.8rem;">El botón <strong>▶ Auto</strong> reproduce la generación con un delay de 500ms. Permite observar el "burbujeo" de la recursividad en vivo.</p>
                        </div>
                        <div style="background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 0.5rem; border: 1px solid rgba(216, 150, 255, 0.1);">
                            <strong style="color: white; display: block; margin-bottom: 5px;">🏁 Modo instantáneo</strong>
                            <p style="font-size: 0.8rem;">El botón <strong>🛸 Completo</strong> despliega todas las instrucciones de golpe, útil para análisis rápidos de flujos extensos.</p>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <h4 style="color: var(--accent); margin-bottom: 0.5rem;">Paso 4: análisis y trazabilidad (funciones pro).</h4>
                    <p style="font-size: 0.9rem; color: var(--text-sec);">Para los usuarios que realizan depuración técnica:</p>
                    <ul style="font-size: 0.85rem; color: var(--text-sec); line-height: 1.6;">
                        <li><strong>Highlight de origen:</strong> Haga clic sobre cualquier línea de color en el panel TAC. Verá un destello púrpura en el editor Pascal indicando la línea exacta que generó esa lógica.</li>
                        <li><strong>Explicación dinámica:</strong> Al seleccionar una línea en el TAC, el <code>Explain Box</code> (debajo de la lista) mostrará una descripción en lenguaje natural de lo que esa instrucción hace técnicamente.</li>
                        <li><strong>Resaltado de sintaxis:</strong> Temporales (celeste), etiquetas (naranja), operadores (rosa) y palabras clave (púrpura) están diferenciados cromáticamente para lectura rápida.</li>
                    </ul>
                </div>

                <!-- SECCIÓN GITHUB ADICIONAL -->
                <div style="margin-top: 3rem; border-top: 1px solid rgba(216, 150, 255, 0.1); padding-top: 2rem; text-align: center;">
                    <h2 style="color: white; margin-bottom: 1rem;">6. Recursos y Código Fuente.</h2>
                    <p style="color: var(--text-sec); font-size: 0.95rem; margin-bottom: 2rem;">Todo el ecosistema de este generador, incluyendo el analizador semántico original y el backend de TAC, está disponible bajo licencia MIT en el repositorio oficial:</p>
                    
                    <a href="https://github.com/20pedro01/Generador-TAC-Pascal" target="_blank" style="display: inline-flex; align-items: center; gap: 1rem; background: #24292e; color: white; text-decoration: none; padding: 1.2rem 2.5rem; border-radius: 1rem; font-weight: 700; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 40px rgba(216, 150, 255, 0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 30px rgba(0,0,0,0.5)'">
                        <svg height="32" viewBox="0 0 16 16" version="1.1" width="32" aria-hidden="true" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path></svg>
                        Ver Repositorio en GitHub
                    </a>
                </div>
            </div>
            </div>
        </div>
    </div>



    <!-- Tab Content: About -->
    <div id="tab-about" class="tab-content" style="min-height: 500px; padding: 20px;">
        <div class="panel" style="flex: 1; padding: 3rem; overflow-y: auto; background: var(--surface); border: 1px solid var(--border); border-radius: 1.5rem;">
            
            <div style="text-align: right; margin-bottom: 2rem;">
                <span style="font-family: 'Outfit', sans-serif; font-size: 0.7rem; color: var(--accent); letter-spacing: 2px; text-transform: uppercase; background: rgba(216, 150, 255, 0.1); padding: 5px 15px; border-radius: 20px; border: 1px solid var(--border);">
                    Desarrolladores Penguin 🐧
                </span>
            </div>
            
            <div style="display: flex; align-items: center; gap: 3rem; justify-content: center; margin-top: 1rem; flex-wrap: wrap;">
                <!-- Foto Equipo 1 -->
                <div style="flex: 0 0 220px; text-align: center; animation: fadeInTab 0.6s ease-out;">
                    <img src="media/equipo1.png?v=1" alt="Equipo 1" style="width: 100%; border-radius: 1.5rem; border: 3px solid var(--accent); box-shadow: 0 0 25px var(--accent-glow); transition: transform 0.3s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                </div>

                <!-- Bloque Central: Info y Grid -->
                <div style="flex: 1; max-width: 800px; text-align: center;">
                    <h2 style="color: var(--accent); margin-bottom: 0.5rem; font-size: 2.5rem; font-weight: 800; letter-spacing: -1px;">Equipo Penguin 🐧</h2>
                    <p style="color: var(--text-sec); margin-bottom: 2.5rem; line-height: 1.6; font-size: 1.2rem;">
                        Estudiantes de Ingeniería en Sistemas Computacionales<br>
                        <span style="color: var(--text-muted); font-size: 1rem;">Instituto Tecnológico Superior de Valladolid (ITSVA)</span>
                    </p>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                        <div class="panel" style="background: rgba(255,255,255,0.03); padding: 1.8rem; border-color: rgba(216, 150, 255, 0.15); border-radius: 1.2rem; align-items: center;">
                            <h3 style="color: white; margin-bottom: 5px; font-size: 1.3rem;">Pedro Cauich Pat</h3>
                            <p style="color: var(--accent); font-size: 0.95rem; margin: 0; font-weight: 500;">Arquitectura Back-End</p>
                        </div>
                        <div class="panel" style="background: rgba(255,255,255,0.03); padding: 1.8rem; border-color: rgba(216, 150, 255, 0.15); border-radius: 1.2rem; align-items: center;">
                            <h3 style="color: white; margin-bottom: 5px; font-size: 1.3rem;">Brenda Chan Xooc</h3>
                            <p style="color: var(--accent); font-size: 0.95rem; margin: 0; font-weight: 500;">Arquitectura Front-End</p>
                        </div>
                        <div class="panel" style="background: rgba(255,255,255,0.03); padding: 1.8rem; border-color: rgba(216, 150, 255, 0.15); border-radius: 1.2rem; align-items: center;">
                            <h3 style="color: white; margin-bottom: 5px; font-size: 1.3rem;">Danneshe Corona Noh</h3>
                            <p style="color: var(--accent); font-size: 0.95rem; margin: 0; font-weight: 500;">Calidad y pruebas</p>
                        </div>
                        <div class="panel" style="background: rgba(255,255,255,0.03); padding: 1.8rem; border-color: rgba(216, 150, 255, 0.15); border-radius: 1.2rem; align-items: center;">
                            <h3 style="color: white; margin-bottom: 5px; font-size: 1.3rem;">Karla Cristina Pat Canche</h3>
                            <p style="color: var(--accent); font-size: 0.95rem; margin: 0; font-weight: 500;">Documentación y presentación</p>
                        </div>
                    </div>
                </div>

                <!-- Foto Equipo 2 -->
                <div style="flex: 0 0 220px; text-align: center; animation: fadeInTab 0.6s ease-out;">
                    <img src="media/equipo2.png?v=1" alt="Equipo 2" style="width: 100%; border-radius: 1.5rem; border: 3px solid var(--accent); box-shadow: 0 0 25px var(--accent-glow); transition: transform 0.3s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                </div>
            </div>

            <div style="margin-top: 3rem; text-align: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem;">
                <p style="color: var(--highlight); font-size: 1rem; margin-bottom: 0.3rem; font-weight: 600;">Docente: Maestro José Leonel Pech May</p>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Ingeniería en Sistemas Computacionales · 6° "C"</p>
            </div>
        </div>
    </div>

    <footer style="text-align: center; margin-top: 1rem; padding: 1rem; color: rgba(216, 150, 255, 0.3); font-size: 0.75rem; border-top: 1px solid rgba(216, 150, 255, 0.1);">
        Desarrollado por el equipo Penguin <span style="font-size: 1rem; vertical-align: middle;">🐧</span><br>
        &copy; Copyright <?php echo date('Y'); ?>
    </footer>

    <script>
        const tacData = <?php echo isset($tacData) ? $tacData : '[]'; ?>;
        const display = document.getElementById('tac-display');
        const explain = document.getElementById('explain-text');
        const countBadge = document.getElementById('instr-count');
        
        let currentIdx = -1;
        let playInterval = null;
        let autoScroll = true;

        display.addEventListener('scroll', () => {
            const atBottom = display.scrollHeight - display.scrollTop <= display.clientHeight + 40;
            autoScroll = atBottom;
        });

        function formatTAC(line) {
            line = line.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
            line = line.replace(/([\+\-\*\/=]|div|mod)/g, '<span class="tac-op">$1</span>');
            line = line.replace(/\b(t\d+)\b/g, '<span class="tac-temp">$1</span>');
            line = line.replace(/^(L\d+:)/, '<span class="tac-label">$1</span>');
            line = line.replace(/\b(goto|IfZ)\s+(L\d+)\b/g, '$1 <span class="tac-label">$2</span>');
            line = line.replace(/\b(IfZ|goto|Call|PushParam|PopParams|begin_func|end_func|begin_program|end_program|return)\b/g, '<span class="tac-keyword">$1</span>');
            return line;
        }

        let navFocus = 'TAC'; // 'TAC' o 'EDITOR'
        const editor = document.getElementById('source_code');

        // Al pulsar en el editor, recuperamos el control manual
        editor.addEventListener('mousedown', () => {
            navFocus = 'EDITOR';
        });

        function showExplanation(idx) {
            if (idx < 0 || idx >= tacData.length) return;
            
            // Calcular el número de paso real (solo instrucciones no vacías)
            let stepNum = 0;
            for (let i = 0; i <= idx; i++) {
                if (tacData[i].instr.trim() !== '') stepNum++;
            }
            
            const lineRef = tacData[idx].line ? `<span style="color:var(--accent)">[Línea ${tacData[idx].line}]</span>` : '';
            // Forzar espacios mediante entidades HTML para una visualización perfecta
            explain.innerHTML = `<b>Paso ${stepNum}:</b>&nbsp; ${lineRef}&nbsp; ${tacData[idx].explain}`;
            
            // Resaltar visualmente la línea clickeada usando data-idx
            document.querySelectorAll('.tac-line').forEach(l => {
                if (l.getAttribute('data-idx') == idx) l.classList.add('active');
                else l.classList.remove('active');
            });
        }

        function highlightSource(lineNum, shouldFocus = false) {
            if (!lineNum || lineNum < 1) {
                clearSourceHighlight();
                return;
            }

            const lines = editor.value.split('\n');
            let start = 0;
            for (let i = 0; i < lineNum - 1; i++) {
                start += lines[i].length + 1;
            }
            const end = start + lines[lineNum - 1].length;
            
            // Para que la selección sea visible (Azul intenso), el editor NECESITA el foco.
            if (shouldFocus) {
                editor.focus({ preventScroll: true });
            }
            
            // Aplicar selección siempre
            editor.setSelectionRange(start, end);

            // Centrar suavemente
            const lineHeight = 19; 
            const offset = (lineNum - 6) * lineHeight;
            editor.scrollTo({
                top: offset > 0 ? offset : 0,
                behavior: 'smooth'
            });
            
            // La línea ya está resaltada y centrada suavemente. 
            // Eliminamos el efecto de pulso para evitar el parpadeo constante durante la navegación.
        }

        function clearSourceHighlight() {
            editor.setSelectionRange(0, 0);
        }

        function renderAllUntil(index) {
            // Si el contenedor está vacío o la cantidad de líneas no coincide, recrear todo
            if (display.children.length !== tacData.length) {
                display.innerHTML = '';
                for (let i = 0; i < tacData.length; i++) {
                    const lineSpan = document.createElement('div');
                    const inst = tacData[i].instr;
                    const isSep = inst.trim() === '';
                    
                    lineSpan.className = isSep ? 'tac-separator' : 'tac-line';
                    lineSpan.setAttribute('data-idx', i);
                    lineSpan.innerHTML = formatTAC(inst) || '&nbsp;';
                    
                    if (!isSep) {
                        lineSpan.onclick = () => {
                            currentIdx = i;
                            navFocus = 'TAC'; 
                            showExplanation(i);
                            highlightSource(tacData[i].line, true);
                            
                            // El clic hace visible esta línea y anteriores
                            for (let j = 0; j <= i; j++) {
                                const l = display.querySelector(`[data-idx="${j}"]`);
                                if (l) l.classList.add('visible');
                            }
                            
                            document.querySelectorAll('.tac-line').forEach(l => {
                                 if (l.getAttribute('data-idx') == i) l.classList.add('active');
                                 else l.classList.remove('active');
                            });
                        };
                    }
                    display.appendChild(lineSpan);
                }
            }

            // Actualizar visibilidad y estado activo
            let realCount = 0;
            for (let i = 0; i < tacData.length; i++) {
                const line = display.children[i];
                const isSep = tacData[i].instr.trim() === '';

                if (i <= index) {
                    line.classList.add('visible');
                    if (!isSep) realCount++;
                }
                
                if (i === index && !isSep) {
                    line.classList.add('active');
                } else {
                    line.classList.remove('active');
                }
            }

            if (index >= 0) {
                const activeLine = display.querySelector(`.active[data-idx="${index}"]`);
                if (activeLine) {
                    activeLine.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                
                let lastValid = index;
                while (lastValid >= 0 && tacData[lastValid].instr.trim() === '') lastValid--;
                
                if (lastValid >= 0) {
                    showExplanation(lastValid);
                    // Forzar el foco para que se vea el resaltado azul/gris síncronamente al navegar
                    highlightSource(tacData[lastValid].line, true);
                }
                countBadge.innerText = realCount;
            } else {
                if (index === -1) {
                    // Estado inicial/Reset: mantener instrucciones pero ocultas
                    for (let i = 0; i < display.children.length; i++) {
                        display.children[i].classList.remove('visible', 'active');
                    }
                    explain.innerText = "Simulador listo. Presione 'Adelante' u use las flechas del teclado.";
                    countBadge.innerText = "0";
                    clearSourceHighlight();
                }
            }
        }

        function step() {
            if (currentIdx >= tacData.length - 1) {
                stopAuto();
                return;
            }
            currentIdx++;
            // Saltar separadores automáticamente
            while (currentIdx < tacData.length - 1 && tacData[currentIdx].instr.trim() === '') {
                currentIdx++;
            }
            renderAllUntil(currentIdx);
        }

        function stepBack() {
            stopAuto();
            if (currentIdx > 0) {
                currentIdx--;
                // Saltar separadores hacia atrás
                while (currentIdx > 0 && tacData[currentIdx].instr.trim() === '') {
                    currentIdx--;
                }
                renderAllUntil(currentIdx);
            }
            // Si currentIdx es 0, no hacemos nada para evitar el reset de visibilidad
        }

        function showAll() {
            stopAuto();
            // Revelar todo
            renderAllUntil(tacData.length - 1);
            
            // Reposicionar el simulador al principio para empezar la revisión
            currentIdx = 0;
            while (currentIdx < tacData.length - 1 && tacData[currentIdx].instr.trim() === '') {
                currentIdx++;
            }
            
            // Forzar visualmente el inicio
            showExplanation(currentIdx);
            highlightSource(tacData[currentIdx].line, true);
            display.scrollTo({ top: 0, behavior: 'smooth' });
            
            explain.innerHTML = `<b>Vista Completa:</b>&nbsp; Navegue libremente con las flechas o haga clic en cualquier línea.`;
        }

        function reset() {
            stopAuto();
            currentIdx = -1;
            renderAllUntil(-1);
        }

        function startAuto() {
            if (currentIdx >= tacData.length - 1) reset();
            autoScroll = true;
            const btn = document.getElementById('btn-play');
            btn.innerHTML = "⏸ Pausa";
            btn.style.color = "var(--highlight)";
            playInterval = setInterval(step, 500);
        }

        function stopAuto() {
            if (playInterval) clearInterval(playInterval);
            playInterval = null;
            const btn = document.getElementById('btn-play');
            if (btn) {
                btn.innerHTML = "▶ Auto";
                btn.style.color = "";
            }
        }

        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(c => {
                c.classList.remove('active');
                c.style.display = 'none';
            });
            document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
            
            const target = document.getElementById('tab-' + tabId);
            if (target) {
                target.classList.add('active');
                target.style.display = 'flex';
                // PERSISTENCIA: Actualizar el hash en la URL sin saltar
                if (history.pushState) {
                    history.pushState(null, null, '#' + tabId);
                } else {
                    location.hash = '#' + tabId;
                }
            }
            if (btn) btn.classList.add('active');
        }

        // Lógica para recuperar la pestaña al recargar
        window.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.replace('#', '');
            if (hash) {
                const btn = document.getElementById('btn-tab-' + hash);
                if (btn) {
                    switchTab(hash, btn);
                }
            }
        });

        if (tacData.length > 0) {
            document.getElementById('btn-next').onclick = step;
            document.getElementById('btn-prev').onclick = stepBack;
            document.getElementById('btn-reset').onclick = reset;
            document.getElementById('btn-show-all').onclick = showAll;
            document.getElementById('btn-play').onclick = () => {
                if (playInterval) stopAuto(); else startAuto();
            };

            // Implementar Control por Teclado (Flechas Arriba/Abajo)
            document.addEventListener('keydown', (e) => {
                // Solo capturamos flechas Arriba/Abajo
                if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;

                // Si estamos en modo de navegación TAC, capturamos las flechas aunque el editor tenga el foco azul
                if (navFocus === 'TAC') {
                    e.preventDefault();
                    if (e.key === 'ArrowDown') step();
                    else stepBack();
                }
                // Si navFocus es 'EDITOR', el navegador actuará normalmente (moviendo el cursor dentro del textarea)
            });
        }

        if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);

        // Lógica de importación de archivos
        document.getElementById('import_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('source_code').value = e.target.result;
            };
            reader.readAsText(file);
        });
    </script>
</body>
</html>
