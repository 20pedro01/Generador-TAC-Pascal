<?php
/**
 * LEXER - Analizador Léxico para Pascal
 * 
 * Responsabilidad: Convertir código fuente Pascal en una secuencia de tokens.
 * Cada token tiene: tipo, valor y número de línea.
 * 
 * NO realiza validación semántica, solo reconocimiento de patrones léxicos.
 */

class Token {
    public $type;
    public $value;
    public $line;

    public function __construct(string $type, string $value, int $line) {
        $this->type = $type;
        $this->value = $value;
        $this->line = $line;
    }
}

class Lexer {
    // Palabras reservadas de Pascal
    private const KEYWORDS = [
        'program', 'var', 'begin', 'end',
        'integer', 'real', 'boolean', 'char', 'string',
        'if', 'then', 'else',
        'while', 'do',
        'for', 'to', 'downto',
        'repeat', 'until',
        'and', 'or', 'not',
        'true', 'false',
        'div', 'mod',
        'write', 'writeln', 'read', 'readln',
        'function', 'procedure', 'const', 'uses', 'type', 'array', 'of'
    ];

    private $source;
    private $pos;
    private $line;
    private $length;
    private $tokens;
    private $errorHandler;

    public function __construct($errorHandler = null) {
        $this->errorHandler = $errorHandler;
    }

    /**
     * Tokeniza el código fuente Pascal completo.
     * @param string $source Código fuente Pascal
     * @return array Lista de objetos Token
     */
    public function tokenize(string $source): array {
        $this->source = $source;
        $this->pos = 0;
        $this->line = 1;
        $this->length = strlen($source);
        $this->tokens = [];

        while ($this->pos < $this->length) {
            $char = $this->source[$this->pos];

            // Saltar espacios en blanco
            if ($char === ' ' || $char === "\t" || $char === "\r") {
                $this->pos++;
                continue;
            }

            // Salto de línea
            if ($char === "\n") {
                $this->line++;
                $this->pos++;
                continue;
            }

            // Comentarios estilo { ... }
            if ($char === '{') {
                $this->skipBlockComment();
                continue;
            }

            // Comentarios estilo (* ... *)
            if ($char === '(' && $this->peek() === '*') {
                $this->skipParenComment();
                continue;
            }

            // Comentarios de línea // ...
            if ($char === '/' && $this->peek() === '/') {
                $this->skipLineComment();
                continue;
            }

            // Comentarios de bloque C /* ... */
            if ($char === '/' && $this->peek() === '*') {
                $this->skipCBlockComment();
                continue;
            }

            // Strings entre comillas simples o acento agudo (para copy-paste corrupto)
            if ($char === "'" || $char === "´") {
                $this->readString();
                continue;
            }

            // Números (enteros y reales)
            if (ctype_digit($char)) {
                $this->readNumber();
                continue;
            }

            // Identificadores y palabras reservadas
            if (ctype_alpha($char) || $char === '_') {
                $this->readIdentifier();
                continue;
            }

            // Operador de asignación :=
            if ($char === ':' && $this->peek() === '=') {
                $this->tokens[] = new Token('T_ASSIGN', ':=', $this->line);
                $this->pos += 2;
                continue;
            }

            // Dos puntos (declaración de tipo)
            if ($char === ':') {
                $this->tokens[] = new Token('T_COLON', ':', $this->line);
                $this->pos++;
                continue;
            }

            // Punto y coma
            if ($char === ';') {
                $this->tokens[] = new Token('T_SEMICOLON', ';', $this->line);
                $this->pos++;
                continue;
            }

            // Punto (fin de programa) o rango (..)
            if ($char === '.') {
                if ($this->peek() === '.') {
                    $this->tokens[] = new Token('T_DOTDOT', '..', $this->line);
                    $this->pos += 2;
                } else {
                    $this->tokens[] = new Token('T_DOT', '.', $this->line);
                    $this->pos++;
                }
                continue;
            }

            // Coma
            if ($char === ',') {
                $this->tokens[] = new Token('T_COMMA', ',', $this->line);
                $this->pos++;
                continue;
            }

            // Paréntesis
            if ($char === '(') {
                $this->tokens[] = new Token('T_LPAREN', '(', $this->line);
                $this->pos++;
                continue;
            }
            if ($char === ')') {
                $this->tokens[] = new Token('T_RPAREN', ')', $this->line);
                $this->pos++;
                continue;
            }

            // Corchetes (arrays)
            if ($char === '[') {
                $this->tokens[] = new Token('T_LBRACKET', '[', $this->line);
                $this->pos++;
                continue;
            }
            if ($char === ']') {
                $this->tokens[] = new Token('T_RBRACKET', ']', $this->line);
                $this->pos++;
                continue;
            }

            // Operadores relacionales
            if ($char === '<') {
                if ($this->peek() === '=') {
                    $this->tokens[] = new Token('T_OP_REL', '<=', $this->line);
                    $this->pos += 2;
                } elseif ($this->peek() === '>') {
                    $this->tokens[] = new Token('T_OP_REL', '<>', $this->line);
                    $this->pos += 2;
                } else {
                    $this->tokens[] = new Token('T_OP_REL', '<', $this->line);
                    $this->pos++;
                }
                continue;
            }

            if ($char === '>') {
                if ($this->peek() === '=') {
                    $this->tokens[] = new Token('T_OP_REL', '>=', $this->line);
                    $this->pos += 2;
                } else {
                    $this->tokens[] = new Token('T_OP_REL', '>', $this->line);
                    $this->pos++;
                }
                continue;
            }

            if ($char === '=') {
                $this->tokens[] = new Token('T_OP_REL', '=', $this->line);
                $this->pos++;
                continue;
            }

            // Operadores aritméticos
            if ($char === '+') {
                $this->tokens[] = new Token('T_OP_ADD', '+', $this->line);
                $this->pos++;
                continue;
            }
            if ($char === '-') {
                $this->tokens[] = new Token('T_OP_SUB', '-', $this->line);
                $this->pos++;
                continue;
            }
            if ($char === '*') {
                if ($this->peek() === '*') {
                    $this->tokens[] = new Token('T_OP_POW', '**', $this->line);
                    $this->pos += 2;
                } else {
                    $this->tokens[] = new Token('T_OP_MUL', '*', $this->line);
                    $this->pos++;
                }
                continue;
            }
            if ($char === '^') {
                $this->tokens[] = new Token('T_OP_POW', '^', $this->line);
                $this->pos++;
                continue;
            }
            if ($char === '/') {
                $this->tokens[] = new Token('T_OP_DIV', '/', $this->line);
                $this->pos++;
                continue;
            }

            // Carácter no reconocido
            if ($this->errorHandler) {
                $this->errorHandler->addError(
                    "Carácter no reconocido: '$char'",
                    $this->line,
                    'léxico'
                );
            }
            $this->pos++;
        }

        // Token de fin de archivo
        $this->tokens[] = new Token('T_EOF', 'EOF', $this->line);

        return $this->tokens;
    }

    /**
     * Retorna el siguiente carácter sin avanzar la posición.
     */
    private function peek(): string {
        if ($this->pos + 1 < $this->length) {
            return $this->source[$this->pos + 1];
        }
        return '';
    }

    /**
     * Lee un identificador o palabra reservada.
     * Pascal es case-insensitive, así que normalizamos a minúsculas para las keywords.
     */
    private function readIdentifier(): void {
        $start = $this->pos;
        while ($this->pos < $this->length && 
               (ctype_alnum($this->source[$this->pos]) || $this->source[$this->pos] === '_')) {
            $this->pos++;
        }
        $value = substr($this->source, $start, $this->pos - $start);
        $lower = strtolower($value);

        if (in_array($lower, self::KEYWORDS)) {
            // Token especial para cada keyword importante
            $type = 'T_' . strtoupper($lower);
            $this->tokens[] = new Token($type, $lower, $this->line);
        } else {
            $this->tokens[] = new Token('T_IDENTIFIER', $value, $this->line);
        }
    }

    /**
     * Lee un literal numérico (entero o real).
     */
    private function readNumber(): void {
        $start = $this->pos;
        $isReal = false;

        while ($this->pos < $this->length && ctype_digit($this->source[$this->pos])) {
            $this->pos++;
        }

        // Parte decimal
        if ($this->pos < $this->length && $this->source[$this->pos] === '.' && 
            $this->pos + 1 < $this->length && ctype_digit($this->source[$this->pos + 1])) {
            $isReal = true;
            $this->pos++; // saltar el punto
            while ($this->pos < $this->length && ctype_digit($this->source[$this->pos])) {
                $this->pos++;
            }
        }

        $value = substr($this->source, $start, $this->pos - $start);
        $type = $isReal ? 'T_NUMBER_REAL' : 'T_NUMBER_INT';
        $this->tokens[] = new Token($type, $value, $this->line);
    }

    /**
     * Lee un literal string entre comillas simples (o acento agudo).
     */
    private function readString(): void {
        $delimiter = $this->source[$this->pos];
        $this->pos++; // saltar comilla inicial
        $value = '';

        while ($this->pos < $this->length) {
            $char = $this->source[$this->pos];
            
            if ($char === $delimiter) {
                // Doble comilla dentro de string = literal escapado
                if ($this->peek() === $delimiter) {
                    $value .= $char; // Preserva el carácter (aunque sea ´)
                    $this->pos += 2;
                } else {
                    $this->pos++; // saltar comilla final
                    break;
                }
            } else {
                if ($char === "\n") {
                    $this->line++;
                }
                $value .= $char;
                $this->pos++;
            }
        }
        // Normalizar a string si usó ´
        $this->tokens[] = new Token('T_STRING', $value, $this->line);
    }

    /**
     * Salta comentarios de bloque { ... }
     */
    private function skipBlockComment(): void {
        $this->pos++; // saltar {
        while ($this->pos < $this->length && $this->source[$this->pos] !== '}') {
            if ($this->source[$this->pos] === "\n") {
                $this->line++;
            }
            $this->pos++;
        }
        if ($this->pos < $this->length) {
            $this->pos++; // saltar }
        }
    }

    /**
     * Salta comentarios (* ... *)
     */
    private function skipParenComment(): void {
        $this->pos += 2; // saltar (*
        while ($this->pos < $this->length) {
            if ($this->source[$this->pos] === '*' && $this->peek() === ')') {
                $this->pos += 2;
                return;
            }
            if ($this->source[$this->pos] === "\n") {
                $this->line++;
            }
            $this->pos++;
        }
    }

    /**
     * Salta comentarios de línea // ...
     */
    private function skipLineComment(): void {
        while ($this->pos < $this->length && $this->source[$this->pos] !== "\n") {
            $this->pos++;
        }
    }
    /**
     * Salta comentarios de bloque estilo C ( slash-star ... star-slash )
     */
    private function skipCBlockComment(): void {
        $this->pos += 2; // saltar /*
        while ($this->pos < $this->length) {
            if ($this->source[$this->pos] === '*' && $this->peek() === '/') {
                $this->pos += 2; // saltar */
                return;
            }
            if ($this->source[$this->pos] === "\n") {
                $this->line++;
            }
            $this->pos++;
        }
    }
}
