<?php
namespace TACGenerator\Core;

use TACGenerator\Output\InstructionList;
use TACGenerator\Managers\TempManager;
use TACGenerator\Managers\LabelManager;

// Carga de dependencias sin Composer
require_once __DIR__ . '/../output/InstructionList.php';
require_once __DIR__ . '/../managers/TempManager.php';
require_once __DIR__ . '/../managers/LabelManager.php';
require_once __DIR__ . '/../nodes/NodeTypes.php';
require_once __DIR__ . '/ASTWalker.php';

/**
 * TACGenerator — Fachada principal del Back-End del compilador Pascal.
 * 
 * Es el único punto de entrada requerido por el visualizador web.
 * Inicializa el entorno, coordina el ASTWalker y devuelve el TAC completo.
 * 
 * NOTA:
 * El Parser del front-end genera arrays asociativos (NO objetos stdClass).
 * Por eso generate() recibe `array $ast`.
 */
class TACGenerator {

    public function __construct() {
        // Reservado para configuración futura (flags de verbosidad, etc.)
    }

    /**
     * Genera el Código en Tres Direcciones (TAC) a partir del AST.
     *
     * @param array $ast  Array asociativo generado por el Parser Pascal.
     * @return string     String multilínea con todas las instrucciones TAC.
     */
    public function generate(array $ast): string {

        // 1. Resetear estado (fundamental si el usuario compila múltiples veces)
        InstructionList::clear();
        TempManager::reset();
        LabelManager::reset();

        // 2. Crear el recorredor DFS postorden
        $walker = new ASTWalker();

        // 3. Iniciar el recorrido desde la raíz del AST
        $walker->generateStatement($ast);

        // 4. Optimizar el flujo (Peephole Optimization)
        InstructionList::optimize();
        
        // 5. Retornar el TAC generado como texto
        return InstructionList::toString();
    }
}
