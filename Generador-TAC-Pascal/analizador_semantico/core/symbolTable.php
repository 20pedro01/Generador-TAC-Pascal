<?php
/**
 * SYMBOL TABLE - Tabla de Símbolos
 * 
 * Responsabilidad: Almacenar y gestionar la información de todos los
 * identificadores declarados durante el análisis semántico.
 * 
 * Soporta múltiples scopes (global + bloques anidados).
 * Cada entrada contiene: nombre, tipo, scope, línea de declaración,
 * estado de inicialización y conteo de usos.
 * 
 * Cumple con la rúbrica: Identificadores, Tipos de datos, Alcance (scope).
 */

class Symbol {
    /** @var string Nombre del identificador */
    public $name;
    /** @var string Tipo de dato (integer, real, boolean, char, string) */
    public $type;
    /** @var string Scope donde fue declarado (global, block_linea_N, etc.) */
    public $scope;
    /** @var int Línea donde fue declarado */
    public $line;
    /** @var bool Si ya se le asignó un valor */
    public $initialized;
    /** @var int Número de veces que fue referenciada (lecturas) */
    public $useCount;
    /** @var string Categoría del símbolo: 'variable', 'constante', 'control_for' */
    public $category;

    public function __construct(string $name, string $type, string $scope, int $line, string $category = 'variable') {
        $this->name = $name;
        $this->type = $type;
        $this->scope = $scope;
        $this->line = $line;
        $this->initialized = false;
        $this->useCount = 0;
        $this->category = $category;
    }
}

class SymbolTable {
    /**
     * Pila de scopes: cada scope es un array asociativo con nombre y símbolos.
     * El scope en la posición 0 es siempre el global.
     * @var array
     */
    private $scopes = [];

    /**
     * Registro histórico de todos los símbolos (para mostrar en la interfaz).
     * Nunca se elimina un símbolo de aquí, incluso cuando se sale de un scope.
     * @var Symbol[]
     */
    private $allSymbols = [];

    /** @var int Contador incremental para nombres únicos de scope */
    private $scopeCounter = 0;

    public function __construct() {
        // Iniciar con el scope global
        $this->pushScope('global');
    }

    /**
     * Crea un nuevo scope y lo apila.
     * En Pascal, cada bloque BEGIN...END puede crear un scope nuevo.
     * 
     * @param string $name Nombre descriptivo del scope
     */
    public function pushScope(string $name = ''): void {
        $this->scopeCounter++;
        $scopeName = $name ?: 'scope_' . $this->scopeCounter;
        $this->scopes[] = [
            'name' => $scopeName,
            'symbols' => []
        ];
    }

    /**
     * Elimina el scope actual de la pila (al salir del bloque).
     * Los símbolos permanecen en allSymbols para el reporte final.
     */
    public function popScope(): void {
        if (count($this->scopes) > 1) {
            array_pop($this->scopes);
        }
    }

    /**
     * Retorna el nombre del scope actual.
     * @return string
     */
    public function getCurrentScopeName(): string {
        $current = end($this->scopes);
        return $current ? $current['name'] : 'unknown';
    }

    /**
     * Retorna la profundidad actual del scope (1 = global).
     * @return int
     */
    public function getScopeDepth(): int {
        return count($this->scopes);
    }

    /**
     * Declara un nuevo símbolo en el scope actual.
     * Retorna false si ya existe en el scope actual (redeclaración).
     * 
     * @param string $name Nombre del identificador
     * @param string $type Tipo de dato
     * @param int $line Línea de declaración
     * @param string $category Categoría: 'variable', 'constante', 'control_for'
     * @return bool True si se declaró exitosamente, false si es redeclaración
     */
    public function declare(string $name, string $type, int $line, string $category = 'variable'): bool {
        $key = strtolower($name); // Pascal es case-insensitive
        $currentScope = &$this->scopes[count($this->scopes) - 1];

        // Verificar redeclaración en el scope actual
        if (isset($currentScope['symbols'][$key])) {
            return false;
        }

        $symbol = new Symbol($name, $type, $currentScope['name'], $line, $category);
        $currentScope['symbols'][$key] = $symbol;

        // Guardar en registro histórico
        $this->allSymbols[] = $symbol;

        return true;
    }

    /**
     * Busca un símbolo en todos los scopes (del más interno al más externo).
     * Implementa la regla de "scope más cercano gana" de Pascal.
     * 
     * @param string $name Nombre del identificador
     * @return Symbol|null El símbolo encontrado o null
     */
    public function lookup(string $name): ?Symbol {
        $key = strtolower($name);

        // Buscar desde el scope más interno al más externo
        for ($i = count($this->scopes) - 1; $i >= 0; $i--) {
            if (isset($this->scopes[$i]['symbols'][$key])) {
                return $this->scopes[$i]['symbols'][$key];
            }
        }
        return null;
    }

    /**
     * Busca un símbolo SOLO en el scope actual.
     * Útil para detectar redeclaraciones.
     * 
     * @param string $name Nombre del identificador
     * @return Symbol|null
     */
    public function lookupCurrentScope(string $name): ?Symbol {
        $key = strtolower($name);
        $currentScope = end($this->scopes);
        if ($currentScope && isset($currentScope['symbols'][$key])) {
            return $currentScope['symbols'][$key];
        }
        return null;
    }

    /**
     * Busca un símbolo en TODOS los scopes y retorna todas las coincidencias.
     * Útil para la detección de ambigüedad.
     * 
     * @param string $name Nombre del identificador
     * @return Symbol[] Array de todos los símbolos con ese nombre
     */
    public function lookupAll(string $name): array {
        $key = strtolower($name);
        $found = [];
        foreach ($this->allSymbols as $symbol) {
            if (strtolower($symbol->name) === $key) {
                $found[] = $symbol;
            }
        }
        return $found;
    }

    /**
     * Marca un símbolo como inicializado (se le asignó un valor).
     * 
     * @param string $name Nombre del identificador
     */
    public function markInitialized(string $name): void {
        $key = strtolower($name);
        for ($i = count($this->scopes) - 1; $i >= 0; $i--) {
            if (isset($this->scopes[$i]['symbols'][$key])) {
                $this->scopes[$i]['symbols'][$key]->initialized = true;
                // También actualizar en el registro histórico
                foreach ($this->allSymbols as $sym) {
                    if (strtolower($sym->name) === $key && $sym->scope === $this->scopes[$i]['name']) {
                        $sym->initialized = true;
                    }
                }
                return;
            }
        }
    }

    /**
     * Incrementa el conteo de uso de un símbolo.
     * Se llama cada vez que se referencia la variable en una expresión.
     * 
     * @param string $name Nombre del identificador
     */
    public function incrementUse(string $name): void {
        $key = strtolower($name);
        for ($i = count($this->scopes) - 1; $i >= 0; $i--) {
            if (isset($this->scopes[$i]['symbols'][$key])) {
                $this->scopes[$i]['symbols'][$key]->useCount++;
                foreach ($this->allSymbols as $sym) {
                    if (strtolower($sym->name) === $key && $sym->scope === $this->scopes[$i]['name']) {
                        $sym->useCount++;
                    }
                }
                return;
            }
        }
    }

    /**
     * Retorna todos los símbolos registrados (para la interfaz).
     * @return Symbol[]
     */
    public function getAllSymbols(): array {
        return $this->allSymbols;
    }

    /**
     * Retorna los símbolos formateados para la interfaz web.
     * Incluye toda la información requerida por la rúbrica.
     * 
     * @return array
     */
    public function getFormattedSymbols(): array {
        $formatted = [];
        foreach ($this->allSymbols as $symbol) {
            $formatted[] = [
                'name' => $symbol->name,
                'type' => $symbol->type,
                'scope' => $symbol->scope,
                'line' => $symbol->line,
                'initialized' => $symbol->initialized ? 'Sí' : 'No',
                'category' => $symbol->category,
                'useCount' => $symbol->useCount
            ];
        }
        return $formatted;
    }

    /**
     * Detecta variables declaradas pero nunca utilizadas.
     * 
     * @return Symbol[] Variables sin uso
     */
    public function findUnusedVariables(): array {
        $unused = [];
        foreach ($this->allSymbols as $symbol) {
            if ($symbol->useCount === 0 && $symbol->category === 'variable') {
                $unused[] = $symbol;
            }
        }
        return $unused;
    }

    /**
     * Verifica si existen símbolos con nombres ambiguos en diferentes scopes.
     * La ambigüedad ocurre cuando el mismo identificador aparece en
     * múltiples scopes, lo que puede causar confusión sobre cuál se referencia.
     * 
     * @return array Mapa [nombre => [Symbol, Symbol, ...]]
     */
    public function findAmbiguities(): array {
        $byName = [];
        foreach ($this->allSymbols as $symbol) {
            if ($symbol->category === 'variable_retorno') continue;
            $key = strtolower($symbol->name);
            $byName[$key][] = $symbol;
        }

        $ambiguities = [];
        foreach ($byName as $name => $symbols) {
            if (count($symbols) > 1) {
                $ambiguities[$name] = $symbols;
            }
        }
        return $ambiguities;
    }

    /**
     * Detecta ambigüedades de tipo: mismo nombre con diferentes tipos.
     * Esto es más grave que la simple duplicación de nombre en scopes distintos.
     * 
     * @return array Mapa [nombre => [Symbol, Symbol, ...]]
     */
    public function findTypeAmbiguities(): array {
        $byName = [];
        foreach ($this->allSymbols as $symbol) {
            if ($symbol->category === 'variable_retorno') continue;
            $key = strtolower($symbol->name);
            $byName[$key][] = $symbol;
        }

        $ambiguities = [];
        foreach ($byName as $name => $symbols) {
            if (count($symbols) > 1) {
                $types = array_unique(array_map(function($s) { return $s->type; }, $symbols));
                if (count($types) > 1) {
                    $ambiguities[$name] = $symbols;
                }
            }
        }
        return $ambiguities;
    }
}
