<?php
namespace TACGenerator\Managers;

/**
 * Gestor de etiquetas (Labels) de control de flujo arquitectónico TAC.
 * Responsable de fabricar y proveer de identificadores numéricamente limpios (L1, L2, L3) 
 * que delimitarán las líneas objetivo de saltos directos (IfZ / Goto) para desvíos.
 */
class LabelManager {
    /**
     * @var int $counter
     * Conteo aislado de la instancia generativa, previniendo choques entre 
     * un bucle while interno sub-asignado y un condicional if maestro.
     */
    private static int $counter = 0;

    /**
     * Genera un nuevo Label indexado incrementalmente.
     * Útil para instanciar el inicio y fin abstractos de bifurcaciones lógicas.
     * 
     * @return string Etiqueta generada alfanumérica sin repetición, lista para formato "L1:".
     * @example L1 = LabelManager::newLabel();
     */
    public static function newLabel(): string {
        self::$counter++;
        return "L" . self::$counter;
    }

    /**
     * Resetea el tracker de etiquetas global.
     * Llama a esta función incondicionalmente a cada inicio dentro del generador 
     * de TAC maestro, evitando sobreponer o escalar saltos entre múltiples documentos fuente. 
     * 
     * @return void
     */
    public static function reset(): void {
        self::$counter = 0;
    }
}
