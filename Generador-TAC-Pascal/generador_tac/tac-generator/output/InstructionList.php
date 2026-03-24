<?php
namespace TACGenerator\Output;

/**
 * Singleton de almacenamiento para la emisión generativa / Búfer del ASTWalker.
 * Acumula todas y cada una de las líneas que traducen el TAC hasta terminar el proceso.
 */
class InstructionList {
    /**
     * @var array Lista estructural temporal estática de la salida tridireccional.
     * Almacena objetos ['instr' => string, 'explain' => string]
     */
    private static array $instructions = [];

    /**
     * Anexa una nueva línea o tupla de control al stack TAC generado.
     * 
     * @param string $instruction Línea TAC nativa estructurada.
     * @param string $explanation Descripción humana de lo que hace esta línea.
     * @param int|null $line Línea del código fuente original.
     * @return void
     */
    public static function add(string $instruction, string $explanation = '', ?int $line = null): void {
        self::$instructions[] = [
            'instr' => $instruction,
            'explain' => $explanation ?: self::guessExplanation($instruction),
            'line' => $line
        ];
    }

    /**
     * Intenta deducir una explicación si no se provee una explícita.
     */
    private static function guessExplanation(string $instr): string {
        if (strpos($instr, '//') === 0) return "Comentario de programa.";
        if (preg_match('/^L\d+:$/', $instr)) return "Definición de etiqueta para saltos.";
        if (strpos($instr, 'IfZ') !== false) return "Prueba de condición: si es cero (falso), salta.";
        if (strpos($instr, 'goto') !== false) return "Salto incondicional de flujo.";
        if (strpos($instr, 'PushParam') !== false) return "Apilando parámetro para llamada a subrutina.";
        if (strpos($instr, 'PopParams') !== false) return "Limpieza de la pila tras los parámetros de la llamada.";
        if (strpos($instr, 'Call') !== false) return "Llamada a función o procedimiento.";
        if (strpos($instr, 'begin_func') !== false) return "Inicio de bloque de función o procedimiento.";
        if (strpos($instr, 'end_func') !== false) return "Fin de bloque de subrutina.";
        if (strpos($instr, 'return') !== false) return "Retorno de valor desde una función.";
        if (strpos($instr, 'begin_program') !== false) return "Punto de entrada al programa principal.";
        if (strpos($instr, 'end_program') !== false) return "Fin del programa principal.";
        if (strpos($instr, '=') !== false) return "Operación de asignación o cálculo intermedio.";
        if (trim($instr) === '') return "Separador visual entre bloques de código para mejorar la legibilidad.";
        return "Instrucción de control.";
    }

    /**
     * Retorna el arreglo completo de objetos de instrucción.
     */
    public static function getFullList(): array {
        return self::$instructions;
    }

    public static function getAll(): array {
        return array_column(self::$instructions, 'instr');
    }

    public static function clear(): void {
        self::$instructions = [];
    }

    public static function toString(): string {
        return implode("\n", self::getAll());
    }

    /**
     * Optimiza la lista de instrucciones mediante técnicas de Mirilla (Peephole Optimization).
     * 1. Jump Threading: Redirige saltos que van a etiquetas que saltan a otro sitio.
     * 2. Dead Label Removal: Elimina etiquetas que ya no son referenciadas por ningún salto.
     */
    public static function optimize(): void {
        $instCount = count(self::$instructions);
        $labelToIdx = [];

        // Paso 1: Mapear etiquetas a su posición
        for ($i = 0; $i < $instCount; $i++) {
            $instr = trim(self::$instructions[$i]['instr']);
            if (preg_match('/^(L\d+):$/', $instr, $matches)) {
                $labelToIdx[$matches[1]] = $i;
            }
        }

        // Paso 2: Identificar redirecciones (Saltos a etiquetas que saltan inmediatamente)
        $redirections = [];
        foreach ($labelToIdx as $label => $idx) {
            // Si la instrucción siguiente a la etiqueta es un goto...
            if ($idx + 1 < $instCount) {
                $nextInstr = trim(self::$instructions[$idx + 1]['instr']);
                if (preg_match('/^goto\s+(L\d+)$/i', $nextInstr, $m)) {
                    $redirections[$label] = $m[1];
                }
            }
        }

        // Paso 3: Aplicar redirecciones (Jump Threading) en gotos y IfZ
        for ($i = 0; $i < $instCount; $i++) {
            $instr = self::$instructions[$i]['instr'];
            
            // Optimizar Goto
            if (preg_match('/^goto\s+(L\d+)$/i', $instr, $m)) {
                $target = $m[1];
                $visited = [$target];
                while (isset($redirections[$target])) {
                    $target = $redirections[$target];
                    if (in_array($target, $visited)) break; // Evitar ciclos infinitos
                    $visited[] = $target;
                }
                self::$instructions[$i]['instr'] = "goto " . $target;
                self::$instructions[$i]['explain'] = "Salto optimizado directamente al destino final.";
            }

            // Optimizar IfZ
            if (preg_match('/^IfZ\s+(.*?)\s+goto\s+(L\d+)$/i', $instr, $m)) {
                $cond = $m[1];
                $target = $m[2];
                $visited = [$target];
                while (isset($redirections[$target])) {
                    $target = $redirections[$target];
                    if (in_array($target, $visited)) break;
                    $visited[] = $target;
                }
                self::$instructions[$i]['instr'] = "IfZ {$cond} goto " . $target;
                self::$instructions[$i]['explain'] = "Condición optimizada para saltar directamente al bloque final.";
            }
        }

        // Paso 4: Eliminar etiquetas huérfanas (que ya no reciben saltos)
        $referencedLabels = [];
        foreach (self::$instructions as $inst) {
            if (preg_match('/goto\s+(L\d+)/i', $inst['instr'], $m)) {
                $referencedLabels[$m[1]] = true;
            }
        }

        $optimized = [];
        foreach (self::$instructions as $inst) {
            if (preg_match('/^(L\d+):$/', $inst['instr'], $m)) {
                if (!isset($referencedLabels[$m[1]])) {
                    continue; // Ignorar etiqueta huérfana
                }
            }
            $optimized[] = $inst;
        }
        self::$instructions = $optimized;
    }

    /**
     * Retorna la lista codificada en JSON para el frontend.
     */
    public static function toJson(): string {
        return json_encode(self::$instructions);
    }
}
