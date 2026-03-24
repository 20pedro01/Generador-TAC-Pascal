<?php
/**
 * PARSER - Analizador Sintáctico para Pascal
 * 
 * Responsabilidad: Consumir la secuencia de tokens del Lexer y construir
 * un AST (Árbol de Sintaxis Abstracta) simplificado.
 * 
 * Nivel de complejidad: Intermedio-Alto académico.
 * Reconoce: PROGRAM, VAR, BEGIN..END, asignaciones, IF..THEN..ELSE,
 *           WHILE..DO, FOR..TO/DOWNTO..DO, REPEAT..UNTIL, WRITE/WRITELN,
 *           READ/READLN, expresiones con operadores aritméticos y relacionales.
 * 
 * Técnica: Recursive Descent Parser (análisis descendente recursivo).
 */

require_once __DIR__ . '/lexer.php';

class Parser {
    private $tokens;
    private $pos;
    private $errorHandler;

    public function __construct($errorHandler) {
        $this->errorHandler = $errorHandler;
    }

    /**
     * Punto de entrada: parsea un programa Pascal completo.
     * @param array $tokens Lista de tokens del Lexer
     * @return array Nodo AST raíz
     */
    public function parse(array $tokens): array {
        $this->tokens = $tokens;
        $this->pos = 0;

        return $this->parseProgram();
    }

    // ─── TOKEN HELPERS ───────────────────────────────────

    /**
     * Retorna el token actual sin avanzar.
     */
    private function current(): Token {
        if ($this->pos < count($this->tokens)) {
            return $this->tokens[$this->pos];
        }
        return new Token('T_EOF', 'EOF', 0);
    }

    /**
     * Retorna el siguiente token sin avanzar (lookahead).
     */
    private function peek(): Token {
        if ($this->pos + 1 < count($this->tokens)) {
            return $this->tokens[$this->pos + 1];
        }
        return new Token('T_EOF', 'EOF', 0);
    }

    /**
     * Consume el token actual y avanza.
     */
    private function advance(): Token {
        $token = $this->current();
        $this->pos++;
        return $token;
    }

    /**
     * Verifica que el token actual sea del tipo esperado y lo consume.
     * Si no coincide, registra un error sintáctico.
     */
    private function expect(string $type): ?Token {
        $token = $this->current();
        if ($token->type === $type) {
            return $this->advance();
        }
        $this->errorHandler->addError(
            "Se esperaba '$type' pero se encontró '{$token->type}' ('{$token->value}')",
            $token->line,
            'sintáctico'
        );
        return null;
    }

    /**
     * Verifica si el token actual es del tipo dado.
     */
    private function check(string $type): bool {
        return $this->current()->type === $type;
    }

    /**
     * Consume el token si coincide con el tipo dado.
     */
    private function match(string $type): bool {
        if ($this->check($type)) {
            $this->advance();
            return true;
        }
        return false;
    }

    // ─── REGLAS GRAMATICALES ─────────────────────────────

    /**
     * Programa ::= PROGRAM id ['(' id_list ')'] ';'
     *              [USES id_list ';']
     *              {CONST|TYPE|VAR|FUNCTION|PROCEDURE sections}
     *              Block '.'
     */
    private function parseProgram(): array {
        $node = ['type' => 'Program', 'line' => $this->current()->line];
        $node['functions'] = [];

        // PROGRAM nombre [(id, id, ...)] ;
        if ($this->check('T_PROGRAM')) {
            $this->advance();
            $nameToken = $this->expect('T_IDENTIFIER');
            $node['name'] = $nameToken ? $nameToken->value : 'unknown';

            // Lista opcional de parámetros de archivo: (INPUT, OUTPUT)
            if ($this->match('T_LPAREN')) {
                if ($this->check('T_IDENTIFIER')) {
                    $this->advance();
                }
                while ($this->match('T_COMMA')) {
                    if ($this->check('T_IDENTIFIER')) {
                        $this->advance();
                    }
                }
                $this->expect('T_RPAREN');
            }

            $this->expect('T_SEMICOLON');
        } else {
            $node['name'] = 'anonymous';
        }

        // USES clause (opcional) — se ignora, solo se consume
        if ($this->check('T_USES')) {
            $this->advance(); // consumir USES
            while (!$this->check('T_SEMICOLON') && !$this->check('T_EOF')) {
                $this->advance();
            }
            $this->match('T_SEMICOLON');
        }

        // Secciones declarativas (pueden aparecer en cualquier orden)
        while ($this->check('T_CONST') || $this->check('T_TYPE') ||
               $this->check('T_VAR') || $this->check('T_FUNCTION') ||
               $this->check('T_PROCEDURE')) {
            if ($this->check('T_CONST')) {
                $node['const_sections'][] = $this->parseConstSection();
            } elseif ($this->check('T_TYPE')) {
                $this->skipTypeSection();
            } elseif ($this->check('T_VAR')) {
                $node['var_sections'][] = $this->parseVarSection();
            } elseif ($this->check('T_FUNCTION')) {
                $node['functions'][] = $this->parseFunctionDecl();
            } elseif ($this->check('T_PROCEDURE')) {
                $node['functions'][] = $this->parseProcedureDecl();
            }
        }

        // Bloque principal BEGIN...END
        $node['body'] = $this->parseBlock();

        // Punto final
        $node['end_line'] = $this->current()->line;
        $this->match('T_DOT');

        return $node;
    }

    /**
     * ConstSection ::= CONST (id '=' Expression ';')+
     */
    private function parseConstSection(): array {
        $this->advance(); // consumir CONST
        $declarations = [];

        while ($this->check('T_IDENTIFIER')) {
            $idToken = $this->advance();
            $this->expect('T_OP_REL'); // '='
            $value = $this->parseExpression();
            $this->expect('T_SEMICOLON');
            $declarations[] = [
                'name' => $idToken->value,
                'value' => $value,
                'line' => $idToken->line
            ];
        }

        return ['type' => 'ConstSection', 'declarations' => $declarations];
    }

    /**
     * Salta la sección TYPE completa (no soportada semánticamente).
     * Consume tokens hasta encontrar la siguiente sección.
     */
    private function skipTypeSection(): void {
        $this->advance(); // consumir TYPE
        $sectionKeywords = ['T_VAR', 'T_BEGIN', 'T_FUNCTION', 'T_PROCEDURE', 'T_CONST', 'T_TYPE', 'T_EOF'];
        while (!in_array($this->current()->type, $sectionKeywords)) {
            $this->advance();
        }
    }

    /**
     * FunctionDecl ::= FUNCTION id '(' [ParamList] ')' ':' Type ';'
     *                  [CONST|VAR sections] Block ';'
     */
    private function parseFunctionDecl(): array {
        $line = $this->current()->line;
        $this->advance(); // consumir FUNCTION

        $nameToken = $this->expect('T_IDENTIFIER');
        $name = $nameToken ? $nameToken->value : 'unknown';

        // Parámetros
        $params = [];
        if ($this->match('T_LPAREN')) {
            if (!$this->check('T_RPAREN')) {
                $params = $this->parseParamList();
            }
            $this->expect('T_RPAREN');
        }

        // Tipo de retorno
        $this->expect('T_COLON');
        $returnTypeToken = $this->advance();
        $returnType = strtolower($returnTypeToken->value);

        $this->expect('T_SEMICOLON');

        // Secciones locales opcionales (VAR, CONST)
        $varSections = [];
        $constSections = [];
        while ($this->check('T_VAR') || $this->check('T_CONST')) {
            if ($this->check('T_VAR')) {
                $varSections[] = $this->parseVarSection();
            } else {
                $constSections[] = $this->parseConstSection();
            }
        }

        // Cuerpo de la función
        $body = $this->parseBlock();
        $this->expect('T_SEMICOLON');

        return [
            'type' => 'FunctionDecl',
            'name' => $name,
            'params' => $params,
            'return_type' => $returnType,
            'var_sections' => $varSections,
            'const_sections' => $constSections,
            'body' => $body,
            'line' => $line
        ];
    }

    /**
     * ProcedureDecl ::= PROCEDURE id ['(' [ParamList] ')'] ';'
     *                   [CONST|VAR sections] Block ';'
     */
    private function parseProcedureDecl(): array {
        $line = $this->current()->line;
        $this->advance(); // consumir PROCEDURE

        $nameToken = $this->expect('T_IDENTIFIER');
        $name = $nameToken ? $nameToken->value : 'unknown';

        // Parámetros opcionales
        $params = [];
        if ($this->match('T_LPAREN')) {
            if (!$this->check('T_RPAREN')) {
                $params = $this->parseParamList();
            }
            $this->expect('T_RPAREN');
        }

        $this->expect('T_SEMICOLON');

        // Secciones locales opcionales
        $varSections = [];
        $constSections = [];
        while ($this->check('T_VAR') || $this->check('T_CONST')) {
            if ($this->check('T_VAR')) {
                $varSections[] = $this->parseVarSection();
            } else {
                $constSections[] = $this->parseConstSection();
            }
        }

        // Cuerpo del procedimiento
        $body = $this->parseBlock();
        $this->expect('T_SEMICOLON');

        return [
            'type' => 'ProcedureDecl',
            'name' => $name,
            'params' => $params,
            'var_sections' => $varSections,
            'const_sections' => $constSections,
            'body' => $body,
            'line' => $line
        ];
    }

    /**
     * ParamList ::= ParamGroup (';' ParamGroup)*
     * ParamGroup ::= [VAR|CONST] IdList ':' Type
     */
    private function parseParamList(): array {
        $params = [];
        $params = array_merge($params, $this->parseParamGroup());
        while ($this->match('T_SEMICOLON')) {
            $params = array_merge($params, $this->parseParamGroup());
        }
        return $params;
    }

    private function parseParamGroup(): array {
        $modifier = null;
        if ($this->check('T_VAR') || $this->check('T_CONST')) {
            $modifier = $this->advance()->value;
        }

        $identifiers = [];
        $idToken = $this->expect('T_IDENTIFIER');
        if ($idToken) {
            $identifiers[] = ['name' => $idToken->value, 'line' => $idToken->line];
        }
        while ($this->match('T_COMMA')) {
            $idToken = $this->expect('T_IDENTIFIER');
            if ($idToken) {
                $identifiers[] = ['name' => $idToken->value, 'line' => $idToken->line];
            }
        }

        $this->expect('T_COLON');
        $typeToken = $this->advance();
        $dataType = strtolower($typeToken->value);

        $params = [];
        foreach ($identifiers as $id) {
            $params[] = [
                'name' => $id['name'],
                'type' => $dataType,
                'modifier' => $modifier,
                'line' => $id['line']
            ];
        }
        return $params;
    }

    /**
     * VarSection ::= VAR (VarDeclaration ';')+
     * VarDeclaration ::= IdList ':' Type
     * IdList ::= id (',' id)*
     */
    private function parseVarSection(): array {
        $declarations = [];
        $this->advance(); // consumir VAR

        // Mientras haya identificadores (declaraciones)
        while ($this->check('T_IDENTIFIER')) {
            $decl = $this->parseVarDeclaration();
            if ($decl) {
                $declarations[] = $decl;
            }
            $this->expect('T_SEMICOLON');
        }

        return ['type' => 'VarSection', 'declarations' => $declarations];
    }

    /**
     * VarDeclaration ::= id (',' id)* ':' Type
     * Type ::= 'integer' | 'real' | 'boolean' | 'char' | 'string' ['[' n ']']
     *        | 'array' '[' expr '..' expr ']' 'of' Type
     *        | identifier (type alias)
     */
    private function parseVarDeclaration(): ?array {
        $identifiers = [];
        $line = $this->current()->line;

        // Lista de identificadores separados por coma
        $idToken = $this->expect('T_IDENTIFIER');
        if ($idToken) {
            $identifiers[] = ['name' => $idToken->value, 'line' => $idToken->line];
        }

        while ($this->match('T_COMMA')) {
            $idToken = $this->expect('T_IDENTIFIER');
            if ($idToken) {
                $identifiers[] = ['name' => $idToken->value, 'line' => $idToken->line];
            }
        }

        // : Tipo
        $this->expect('T_COLON');

        // Verificar si es ARRAY [x..y] OF tipo
        if ($this->check('T_ARRAY')) {
            $this->advance(); // consumir ARRAY
            $this->expect('T_LBRACKET');
            // Saltar la definición del rango: expr..expr
            while (!$this->check('T_RBRACKET') && !$this->check('T_EOF')) {
                $this->advance();
            }
            $this->expect('T_RBRACKET');
            $this->expect('T_OF');
            $typeToken = $this->advance();
            $dataType = strtolower($typeToken->value);
        } else {
            $typeToken = $this->advance();
            $dataType = strtolower($typeToken->value);
            // string[n] - cadena con tamaño
            if ($dataType === 'string' && $this->check('T_LBRACKET')) {
                $this->advance(); // [
                while (!$this->check('T_RBRACKET') && !$this->check('T_EOF')) {
                    $this->advance();
                }
                $this->match('T_RBRACKET');
            }
        }

        return [
            'type' => 'VarDeclaration',
            'identifiers' => $identifiers,
            'data_type' => $dataType,
            'line' => $line
        ];
    }

    /**
     * Block ::= BEGIN StatementList END
     */
    private function parseBlock(): array {
        $line = $this->current()->line;
        $this->expect('T_BEGIN');

        $statements = $this->parseStatementList();

        $endLine = $this->current()->line; // Capturar línea del END
        $this->expect('T_END');

        return [
            'type' => 'Block',
            'statements' => $statements,
            'line' => $line,
            'end_line' => $endLine
        ];
    }

    /**
     * StatementList ::= Statement (';' Statement)*
     */
    private function parseStatementList(): array {
        $statements = [];

        while (!$this->check('T_END') && !$this->check('T_EOF') && !$this->check('T_UNTIL')) {
            $stmt = $this->parseStatement();
            if ($stmt) {
                $statements[] = $stmt;
            }

            // Verificar punto y coma obligatorio para separar sentencias
            if ($this->check('T_SEMICOLON')) {
                $this->advance(); // Consumir ;
            } else {
                // Si NO hay punto y coma, debe ser el final del bloque
                if (!$this->check('T_END') && !$this->check('T_UNTIL') && !$this->check('T_EOF')) {
                    $this->errorHandler->addError(
                        "Se esperaba ';' después de la sentencia",
                        $this->current()->line,
                        'sintáctico'
                    );
                    // Intentar recuperación simple: si el siguiente es una palabra clave de inicio de sentencia, asumir que faltó ;
                    // Si no, podríamos estar perdidos. Por ahora seguimos.
                }
            }
        }

        return $statements;
    }

    /**
     * Statement ::= Assignment | ProcedureCall | IfStatement | WhileStatement
     *             | ForStatement | RepeatStatement | WriteStatement | ReadStatement | Block
     */
    private function parseStatement(): ?array {
        $token = $this->current();

        switch ($token->type) {
            case 'T_IDENTIFIER':
                // Lookahead: distinguir asignación vs llamada a procedimiento
                if ($this->peek()->type === 'T_LPAREN') {
                    return $this->parseProcedureCall();
                }
                if ($this->peek()->type === 'T_ASSIGN') {
                    return $this->parseAssignment();
                }
                if ($this->peek()->type === 'T_LBRACKET') {
                    // Asignación a elemento de array: id[expr] := expr
                    return $this->parseAssignment();
                }
                // Llamada a procedimiento sin paréntesis (ej: clrscr, readkey)
                return $this->parseProcedureCall();

            case 'T_IF':
                return $this->parseIfStatement();

            case 'T_WHILE':
                return $this->parseWhileStatement();

            case 'T_FOR':
                return $this->parseForStatement();

            case 'T_REPEAT':
                return $this->parseRepeatStatement();

            case 'T_WRITE':
            case 'T_WRITELN':
                return $this->parseWriteStatement();

            case 'T_READ':
            case 'T_READLN':
                return $this->parseReadStatement();

            case 'T_BEGIN':
                return $this->parseBlock();

            case 'T_END':
            case 'T_EOF':
                return null;

            default:
                $this->errorHandler->addError(
                    "Sentencia inesperada: '{$token->value}'",
                    $token->line,
                    'sintáctico'
                );
                $this->advance();
                return null;
        }
    }

    /**
     * Assignment ::= id ['[' Expression ']'] ':=' Expression
     */
    private function parseAssignment(): array {
        $idToken = $this->advance(); // consumir identificador
        $line = $idToken->line;

        // Acceso a array opcional: id[expr]
        $index = null;
        if ($this->match('T_LBRACKET')) {
            $index = $this->parseExpression();
            $this->expect('T_RBRACKET');
        }

        $this->expect('T_ASSIGN');

        $expression = $this->parseExpression();

        $result = [
            'type' => 'Assignment',
            'identifier' => $idToken->value,
            'expression' => $expression,
            'line' => $line
        ];
        if ($index) {
            $result['index'] = $index;
        }
        return $result;
    }

    /**
     * ProcedureCall ::= id '(' [ExpressionList] ')'
     * Soporta llamadas a procedimientos built-in como INC, DEC, etc.
     */
    private function parseProcedureCall(): array {
        $idToken = $this->advance(); // consumir identificador
        $line = $idToken->line;

        $args = [];
        if ($this->match('T_LPAREN')) {
            if (!$this->check('T_RPAREN')) {
                $args[] = $this->parseExpression();
                while ($this->match('T_COMMA')) {
                    $args[] = $this->parseExpression();
                }
            }
            $this->expect('T_RPAREN');
        }

        return [
            'type' => 'ProcedureCall',
            'name' => $idToken->value,
            'arguments' => $args,
            'line' => $line
        ];
    }

    /**
     * IfStatement ::= IF Expression THEN Statement [ELSE Statement]
     */
    private function parseIfStatement(): array {
        $line = $this->current()->line;
        $this->advance(); // consumir IF

        $condition = $this->parseExpression();

        $this->expect('T_THEN');

        $thenBranch = $this->parseStatement();

        $elseBranch = null;
        if ($this->match('T_ELSE')) {
            $elseBranch = $this->parseStatement();
        }

        return [
            'type' => 'IfStatement',
            'condition' => $condition,
            'then_branch' => $thenBranch,
            'else_branch' => $elseBranch,
            'line' => $line
        ];
    }

    /**
     * WhileStatement ::= WHILE Expression DO Statement
     */
    private function parseWhileStatement(): array {
        $line = $this->current()->line;
        $this->advance(); // consumir WHILE

        $condition = $this->parseExpression();

        $this->expect('T_DO');

        $body = $this->parseStatement();

        return [
            'type' => 'WhileStatement',
            'condition' => $condition,
            'body' => $body,
            'line' => $line
        ];
    }

    /**
     * ForStatement ::= FOR id ':=' Expression (TO|DOWNTO) Expression DO Statement
     */
    private function parseForStatement(): array {
        $line = $this->current()->line;
        $this->advance(); // consumir FOR

        $varToken = $this->expect('T_IDENTIFIER');
        $varName = $varToken ? $varToken->value : 'unknown';

        $this->expect('T_ASSIGN');
        $startExpr = $this->parseExpression();

        // TO o DOWNTO
        $direction = 'to';
        if ($this->check('T_TO')) {
            $this->advance();
            $direction = 'to';
        } elseif ($this->check('T_DOWNTO')) {
            $this->advance();
            $direction = 'downto';
        } else {
            $this->errorHandler->addError(
                "Se esperaba 'to' o 'downto' en sentencia for",
                $this->current()->line,
                'sintáctico'
            );
        }

        $endExpr = $this->parseExpression();

        $this->expect('T_DO');

        $body = $this->parseStatement();

        return [
            'type' => 'ForStatement',
            'variable' => $varName,
            'start' => $startExpr,
            'end' => $endExpr,
            'direction' => $direction,
            'body' => $body,
            'line' => $line
        ];
    }

    /**
     * RepeatStatement ::= REPEAT StatementList UNTIL Expression
     */
    private function parseRepeatStatement(): array {
        $line = $this->current()->line;
        $this->advance(); // consumir REPEAT

        $statements = $this->parseStatementList();

        $this->expect('T_UNTIL');

        $condition = $this->parseExpression();

        return [
            'type' => 'RepeatStatement',
            'statements' => $statements,
            'condition' => $condition,
            'line' => $line
        ];
    }

    /**
     * WriteStatement ::= (WRITE|WRITELN) '(' ExpressionList ')'
     */
    private function parseWriteStatement(): array {
        $token = $this->advance();
        $line = $token->line;
        $funcName = $token->value;

        // Helper para procesar un argumento con formato opcional
        $parseArg = function() {
            $arg = $this->parseExpression();
            // Formato opcional :ancho [:decimales]
            if ($this->check('T_COLON')) {
                $this->advance();
                $this->parseExpression(); // ancho
                if ($this->check('T_COLON')) {
                    $this->advance();
                    $this->parseExpression(); // decimales
                }
            }
            return $arg;
        };

        $args = [];
        if ($this->match('T_LPAREN')) {
            $args[] = $parseArg();
            while ($this->match('T_COMMA')) {
                $args[] = $parseArg();
            }
            $this->expect('T_RPAREN');
        }

        return [
            'type' => 'WriteStatement',
            'function' => $funcName,
            'arguments' => $args,
            'line' => $line
        ];
    }

    /**
     * ReadStatement ::= (READ|READLN) '(' IdList ')'
     */
    private function parseReadStatement(): array {
        $token = $this->advance();
        $line = $token->line;
        $funcName = $token->value;

        $vars = [];
        // READ/READLN puede tener lista de argumentos opcional
        if ($this->check('T_LPAREN')) {
            $this->advance(); // consumir (
            
            // Primer argumento
            if ($this->check('T_IDENTIFIER')) {
                $idToken = $this->advance();
                $var = ['name' => $idToken->value, 'line' => $idToken->line];
                
                // Verificar si es acceso a array
                if ($this->check('T_LBRACKET')) {
                    $this->advance(); // [
                    $var['index'] = $this->parseExpression();
                    $this->expect('T_RBRACKET');
                    $var['type'] = 'ArrayAccess';
                } else {
                    $var['type'] = 'Identifier';
                }
                $vars[] = $var;
            }

            // Argumentos subsiguientes
            while ($this->match('T_COMMA')) {
                $idToken = $this->expect('T_IDENTIFIER');
                if ($idToken) {
                    $var = ['name' => $idToken->value, 'line' => $idToken->line];
                    
                    // Verificar si es acceso a array
                    if ($this->check('T_LBRACKET')) {
                        $this->advance(); // [
                        $var['index'] = $this->parseExpression();
                        $this->expect('T_RBRACKET');
                        $var['type'] = 'ArrayAccess';
                    } else {
                        $var['type'] = 'Identifier';
                    }
                    $vars[] = $var;
                }
            }
            $this->expect('T_RPAREN');
        }

        return [
            'type' => 'ReadStatement',
            'function' => $funcName,
            'variables' => $vars,
            'line' => $line
        ];
    }

    // ─── EXPRESIONES ─────────────────────────────────────

    /**
     * Expression ::= SimpleExpression (RelOp SimpleExpression)*
     */
    private function parseExpression(): array {
        $left = $this->parseSimpleExpression();

        while ($this->check('T_OP_REL')) {
            $op = $this->advance();
            $right = $this->parseSimpleExpression();
            $left = [
                'type' => 'BinaryOp',
                'operator' => $op->value,
                'left' => $left,
                'right' => $right,
                'line' => $op->line
            ];
        }

        return $left;
    }

    /**
     * SimpleExpression ::= Term (('+' | '-' | 'or') Term)*
     */
    private function parseSimpleExpression(): array {
        // Signo unario opcional
        $unary = null;
        if ($this->check('T_OP_ADD') || $this->check('T_OP_SUB')) {
            $unary = $this->advance()->value;
        }

        $left = $this->parseTerm();

        if ($unary === '-') {
            $left = [
                'type' => 'UnaryOp',
                'operator' => '-',
                'operand' => $left,
                'line' => $left['line'] ?? 0
            ];
        }

        while ($this->check('T_OP_ADD') || $this->check('T_OP_SUB') || $this->check('T_OR')) {
            $op = $this->advance();
            $right = $this->parseTerm();
            $left = [
                'type' => 'BinaryOp',
                'operator' => $op->value,
                'left' => $left,
                'right' => $right,
                'line' => $op->line
            ];
        }

        return $left;
    }

    /**
     * Term ::= Factor (('*' | '/' | 'div' | 'mod' | 'and') Factor)*
     */
    private function parseTerm(): array {
        $left = $this->parsePower();

        while ($this->check('T_OP_MUL') || $this->check('T_OP_DIV') || 
               $this->check('T_DIV') || $this->check('T_MOD') || $this->check('T_AND')) {
            $op = $this->advance();
            $right = $this->parseFactor();
            $left = [
                'type' => 'BinaryOp',
                'operator' => $op->value,
                'left' => $left,
                'right' => $right,
                'line' => $op->line
            ];
        }

        return $left;
    }

    /**
     * Power ::= Factor ('**' Factor)*
     */
    private function parsePower(): array {
        $left = $this->parseFactor();

        while ($this->check('T_OP_POW')) {
            $op = $this->advance();
            $right = $this->parseFactor();
            $left = [
                'type' => 'BinaryOp',
                'operator' => $op->value,
                'left' => $left,
                'right' => $right,
                'line' => $op->line
            ];
        }

        return $left;
    }

    /**
     * Factor ::= NUMBER | STRING | BOOLEAN | IDENTIFIER | NOT Factor | '(' Expression ')'
     */
    private function parseFactor(): array {
        $token = $this->current();

        // Literal entero
        if ($token->type === 'T_NUMBER_INT') {
            $this->advance();
            return ['type' => 'Literal', 'value' => $token->value, 'data_type' => 'integer', 'line' => $token->line];
        }

        // Literal real
        if ($token->type === 'T_NUMBER_REAL') {
            $this->advance();
            return ['type' => 'Literal', 'value' => $token->value, 'data_type' => 'real', 'line' => $token->line];
        }

        // Literal string
        if ($token->type === 'T_STRING') {
            $this->advance();
            $dt = (strlen($token->value) === 1) ? 'char' : 'string';
            return ['type' => 'Literal', 'value' => $token->value, 'data_type' => $dt, 'line' => $token->line];
        }

        // Literal booleano
        if ($token->type === 'T_TRUE' || $token->type === 'T_FALSE') {
            $this->advance();
            return ['type' => 'Literal', 'value' => $token->value, 'data_type' => 'boolean', 'line' => $token->line];
        }

        // Identificador (variable, llamada a función, o acceso a array)
        if ($token->type === 'T_IDENTIFIER') {
            $this->advance();
            // Lookahead: si sigue '(' es una llamada a función (sqrt, abs, etc.)
            if ($this->check('T_LPAREN')) {
                $this->advance(); // consumir '('
                $args = [];
                if (!$this->check('T_RPAREN')) {
                    $args[] = $this->parseExpression();
                    while ($this->match('T_COMMA')) {
                        $args[] = $this->parseExpression();
                    }
                }
                $this->expect('T_RPAREN');
                return [
                    'type' => 'FunctionCall',
                    'name' => $token->value,
                    'arguments' => $args,
                    'line' => $token->line
                ];
            }
            // Acceso a array: id[expr]
            if ($this->check('T_LBRACKET')) {
                $this->advance(); // consumir '['
                $indexExpr = $this->parseExpression();
                $this->expect('T_RBRACKET');
                return [
                    'type' => 'ArrayAccess',
                    'name' => $token->value,
                    'index' => $indexExpr,
                    'line' => $token->line
                ];
            }
            return ['type' => 'Identifier', 'name' => $token->value, 'line' => $token->line];
        }

        // NOT (negación lógica)
        if ($token->type === 'T_NOT') {
            $this->advance();
            $factor = $this->parseFactor();
            return ['type' => 'UnaryOp', 'operator' => 'not', 'operand' => $factor, 'line' => $token->line];
        }

        // Expresión entre paréntesis
        if ($token->type === 'T_LPAREN') {
            $this->advance();
            $expr = $this->parseExpression();
            $this->expect('T_RPAREN');
            if (is_array($expr)) {
                $expr['paren_level'] = ($expr['paren_level'] ?? 0) + 1;
            }
            return $expr;
        }

        // Error: factor inesperado
        $this->errorHandler->addError(
            "Factor inesperado: '{$token->value}' ({$token->type})",
            $token->line,
            'sintáctico'
        );
        $this->advance();
        return ['type' => 'Error', 'value' => $token->value, 'line' => $token->line];
    }
}
