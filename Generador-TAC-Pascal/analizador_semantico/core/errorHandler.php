<?php
/**
 * ERROR HANDLER - Gestor centralizado de errores
 * 
 * Responsabilidad: Recolectar, clasificar y formatear todos los errores
 * detectados durante las fases de análisis (léxico, sintáctico, semántico).
 * 
 * La interfaz web consulta este componente para mostrar resultados.
 */

class SemanticError {
    public $message;
    public $line;
    public $type;      // 'léxico', 'sintáctico', 'semántico'
    public $severity;  // 'error', 'warning'

    public function __construct(string $message, int $line, string $type, string $severity = 'error') {
        $this->message = $message;
        $this->line = $line;
        $this->type = $type;
        $this->severity = $severity;
    }
}

class ErrorHandler {
    private $errors = [];

    /**
     * Agrega un error a la lista.
     */
    public function addError(string $message, int $line, string $type, string $severity = 'error'): void {
        $this->errors[] = new SemanticError($message, $line, $type, $severity);
    }

    /**
     * Verifica si hay errores registrados.
     */
    public function hasErrors(): bool {
        return count($this->errors) > 0;
    }

    /**
     * Retorna true si hay errores graves (no warnings).
     */
    public function hasCriticalErrors(): bool {
        foreach ($this->errors as $error) {
            if ($error->severity === 'error') {
                return true;
            }
        }
        return false;
    }

    /**
     * Retorna todos los errores.
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Retorna errores filtrados por tipo.
     */
    public function getErrorsByType(string $type): array {
        return array_filter($this->errors, function($e) use ($type) {
            return $e->type === $type;
        });
    }

    /**
     * Retorna el conteo total de mensajes.
     */
    public function getMessageCount(): int {
        return count($this->errors);
    }

    /**
     * Retorna el conteo de errores críticos.
     */
    public function getErrorCount(): int {
        return count(array_filter($this->errors, function($e) {
            return $e->severity === 'error';
        }));
    }

    /**
     * Retorna el conteo de advertencias.
     */
    public function getWarningCount(): int {
        return count(array_filter($this->errors, function($e) {
            return $e->severity === 'warning';
        }));
    }

    /**
     * Retorna errores formateados para la interfaz web.
     */
    public function getFormattedErrors(): array {
        $formatted = [];
        foreach ($this->errors as $error) {
            $icon = $error->severity === 'error' ? '❌' : '⚠️';
            $formatted[] = [
                'icon' => $icon,
                'message' => $error->message,
                'line' => $error->line,
                'type' => ucfirst($error->type),
                'severity' => $error->severity
            ];
        }
        // Ordenar por línea
        usort($formatted, function($a, $b) {
            return $a['line'] - $b['line'];
        });
        return $formatted;
    }

    /**
     * Limpia todos los errores.
     */
    public function clear(): void {
        $this->errors = [];
    }
}
