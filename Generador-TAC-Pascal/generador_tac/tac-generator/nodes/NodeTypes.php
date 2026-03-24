<?php
namespace TACGenerator\Nodes;

/**
 * NodeTypes — Contrato de Tipos de Nodo AST
 * 
 * Define constantes inmutables que mapean exactamente los tipos de nodo
 * que genera el Parser del front-end (Analizador Semántico Pascal).
 * 
 * AUDITADO por: AST Integration Agent — 2026-03-15
 * 
 * Regla de mapeo (parser real → constante TAC):
 *   'Program'       → PROGRAM
 *   'Block'         → BLOCK
 *   'Assignment'    → ASSIGNMENT
 *   'Identifier'    → IDENTIFIER  (el parser NO genera 'Variable')
 *   'Literal'       → LITERAL     (el parser NO genera 'Constant')
 *   'BinaryOp'      → BINARY_OP   (cubre aritméticos Y relacionales Y lógicos)
 *   'UnaryOp'       → UNARY_OP
 *   'IfStatement'   → IF_STATEMENT
 *   'WhileStatement'→ WHILE_STATEMENT
 *   'ForStatement'  → FOR_STATEMENT
 *   'FunctionCall'  → FUNCTION_CALL
 *   'ProcedureCall' → PROCEDURE_CALL
 *   'ArrayAccess'   → ARRAY_ACCESS
 *   'WriteStatement'→ WRITE_STATEMENT
 *   'ReadStatement' → READ_STATEMENT
 */
class NodeTypes {

    // ─── Raíz y bloques ──────────────────────────────────────────────────────
    
    /** Nodo raíz del programa. Parser genera: ['type' => 'Program', 'body' => ...] */
    public const PROGRAM = 'Program';
    
    /** Bloque begin...end. Parser genera: ['type' => 'Block', 'statements' => [...]] */
    public const BLOCK = 'Block';

    // ─── Sentencias ──────────────────────────────────────────────────────────
    
    /**
     * Asignación. Parser genera:
     * ['type' => 'Assignment', 'identifier' => string, 'expression' => node]
     * NOTA: usa 'identifier' (string), NO un nodo Variable separado.
     */
    public const ASSIGNMENT = 'Assignment';

    // ─── Hojas / Valores atómicos ─────────────────────────────────────────────
    
    /**
     * Variable o identificador. Parser genera:
     * ['type' => 'Identifier', 'name' => string]
     * (El AST_CONTRACT lo llama 'Variable' pero el parser real emite 'Identifier')
     */
    public const IDENTIFIER = 'Identifier';
    
    /**
     * Constante / literal numérico, booleano o string. Parser genera:
     * ['type' => 'Literal', 'value' => mixed, 'data_type' => string]
     * (El AST_CONTRACT lo llama 'Constant' pero el parser real emite 'Literal')
     */
    public const LITERAL = 'Literal';

    // ─── Operaciones ──────────────────────────────────────────────────────────
    
    /**
     * Operación binaria (aritmética, relacional O lógica).
     * El parser NO distingue entre BinaryOp/RelationalOp/LogicalOp:
     * TODOS se emiten como 'BinaryOp' con su 'operator' correspondiente.
     * Parser genera: ['type' => 'BinaryOp', 'operator' => string, 'left' => node, 'right' => node]
     */
    public const BINARY_OP = 'BinaryOp';
    
    /**
     * Operador unario (negación aritmética '-' o lógica 'not').
     * Parser genera: ['type' => 'UnaryOp', 'operator' => string, 'operand' => node]
     */
    public const UNARY_OP = 'UnaryOp';

    // ─── Control de flujo ────────────────────────────────────────────────────
    
    /**
     * Sentencia IF (con o sin ELSE). Parser genera:
     * ['type' => 'IfStatement', 'condition' => node, 'then_branch' => node, 'else_branch' => node|null]
     */
    public const IF_STATEMENT = 'IfStatement';
    
    /**
     * Sentencia WHILE. Parser genera:
     * ['type' => 'WhileStatement', 'condition' => node, 'body' => node]
     */
    public const WHILE_STATEMENT = 'WhileStatement';
    
    /**
     * Sentencia FOR. Parser genera:
     * ['type' => 'ForStatement', 'variable' => string, 'start' => node, 'end' => node,
     *  'direction' => 'to'|'downto', 'body' => node]
     */
    public const FOR_STATEMENT = 'ForStatement';

    // ─── Subrutinas ───────────────────────────────────────────────────────────
    
    /**
     * Llamada a función (en expresión, retorna valor).
     * Parser genera: ['type' => 'FunctionCall', 'name' => string, 'arguments' => [...]]
     */
    public const FUNCTION_CALL = 'FunctionCall';
    
    /**
     * Llamada a procedimiento (como sentencia, no retorna valor).
     * Parser genera: ['type' => 'ProcedureCall', 'name' => string, 'arguments' => [...]]
     */
    public const PROCEDURE_CALL = 'ProcedureCall';
    
    /** Definición de función. Parser genera: type => FunctionDecl */
    public const FUNCTION_DECL = 'FunctionDecl';
    
    /** Definición de procedimiento. Parser genera: type => ProcedureDecl */
    public const PROCEDURE_DECL = 'ProcedureDecl';

    // ─── Nodos extendidos ────────────────────────────────────────────────────
    
    /**
     * Acceso a elemento de array: id[expr].
     * Parser genera: ['type' => 'ArrayAccess', 'name' => string, 'index' => node]
     */
    public const ARRAY_ACCESS = 'ArrayAccess';
    
    /**
     * Write / Writeln (sentencia de salida).
     * Parser genera: ['type' => 'WriteStatement', 'function' => string, 'arguments' => [...]]
     */
    public const WRITE_STATEMENT = 'WriteStatement';
    
    /**
     * Read / Readln (sentencia de entrada).
     * Parser genera: ['type' => 'ReadStatement', 'function' => string, 'variables' => [...]]
     */
    public const READ_STATEMENT = 'ReadStatement';

    // ─── Operadores que se clasifican como relacionales ───────────────────────
    
    /**
     * Conjunto de operadores relacionales reconocidos.
     * Útil para identificar BinaryOp de tipo relacional dentro del ASTWalker.
     */
    public const RELATIONAL_OPERATORS = ['<', '>', '<=', '>=', '=', '<>'];
    
    /**
     * Conjunto de operadores lógicos reconocidos.
     */
    public const LOGICAL_OPERATORS = ['and', 'or'];
    
    /**
     * Conjunto de operadores aritméticos reconocidos.
     */
    public const ARITHMETIC_OPERATORS = ['+', '-', '*', '/', 'div', 'mod'];
}
