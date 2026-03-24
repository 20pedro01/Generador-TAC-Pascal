<?php
namespace TACGenerator\Managers;

/**
 * Gestor de variables temporales.
 * Responsable de generar y mantener un registro seguro y persistente en memoria 
 * de variables temporales durante la evaluación activa del TAC (t1, t2, t3...).
 */
class TempManager {
    /**
     * @var int $counter
     * Contador global de temporales autoincremental en compilación activa.
     */
    private static int $counter = 0;

    /**
     * Genera una nueva variable temporal incrementando el secuencial numérico.
     * Útil para albergar "t-n" bloques de operaciones matemáticas abstraídas en tuplas-ast.
     * 
     * @return string Variable temporal generada garantizada como única (ej. "t1").
     * @example t1 = TempManager::newTemp();
     */
    public static function newTemp(): string {
        self::$counter++;
        return "t" . self::$counter;
    }

    /**
     * Obtiene el ID del último temporal generado sin incrementarlo.
     * 
     * @return int El valor actual del contador.
     */
    public static function getLastId(): int {
        return self::$counter;
    }

    /**
     * Reinicia el contador de temporales.
     * Obligatorio llamarle antes de iniciar un nuevo ciclo de parsing/generación.
     * 
     * @return void
     */
    public static function reset(): void {
        self::$counter = 0;
    }
}
