# BRAIN DEL PROYECTO: Generador de TAC Pascal

Este documento central (`PROJECT_BRAIN.md`) condensa la arquitectura, flujo y especificaciones del **Generador de Código en Tres Direcciones (TAC)** para el entorno de estructuración y compilación en Pascal.

Actúa como la memoria base y guía integral definitiva (Contrato general) del proyecto, debiendo ser respetado por todos los agentes de desarrollo, pruebas y revisión de software.

---

## 1. OBJETIVO DEL PROYECTO

El proyecto consiste en el desarrollo del **Back-End Lógico de un compilador de Pascal** a través de un **Generador de Código Intermedio (TAC)** escrito en PHP. 

El sistema debe tomar las estructuras semánticas y sintácticas generadas previamente en el flujo del compilador (Front-End) y traducirlas secuencialmente a instrucciones independientes y atómicas compuestas por un máximo de **tres operandos**. Finalmente, el bloque saliente debe presentarse en una interfaz visual que lo exponga claramente al usuario final o analista.

---

## 2. ARQUITECTURA DEL COMPILADOR

La integración de la solución se anida y empalma directamente sobre las fases previas de resolución, componiendo el siguiente ciclo de vida modular:

### Front-End (Existente)
1. **Analizador Léxico**: Tokeniza e identifica unidades.
2. **Analizador Sintáctico (Parser)**: Garantiza la pertenencia del lenguaje.
3. **Analizador Semántico**: Determina consistencia contextual (Tipos, variables no inicializadas) apoyado en la Tabla de Símbolos.
4. **AST**: Desliega en la memoria principal el Árbol Sintáctico Abstracto normado.

### Back-End (Desarrollo TAC)
5. **TAC Generator**: Módulo núcleo (en PHP) que toma las sentencias desde la raíz del AST.
6. **Instruction List**: Generación lineal del Código en 3 Direcciones, respetando unicidad de Variables Temporales (`t1`, `t2`) y etiquetas limitantes de saltos (`L1`, `L2`).
7. **Visualizador TAC**: Renderizado en el navegador emitiendo side-by-side de código original contra su homologo analítico TAC.

---

## 3. ESTRUCTURA DEL PROYECTO

Distribución esquematizada de los directorios en el repositorio para evitar un nivel de acoplamiento indeseado, implementable en PHP con diseño orientado a objetos:

```text
/tac-generator          # Módulo Backend
  ├── /core             
  │    ├── TACGenerator.php    # Facada y punto de entrada algorítmico.
  │    └── ASTWalker.php       # Motor Visitador de Ramas (Postorden).
  ├── /managers         
  │    ├── TempManager.php     # Despacho atómico de (t1, t2...).
  │    └── LabelManager.php    # Despacho serial de etiquetas de salto (L1, L2...).
  ├── /nodes            
  │    └── NodeTypes.php       # Contrato enum del tipado de AST (`ASSIGNMENT`, `IF`, etc.).
  ├── /output           
  │    └── InstructionList.php # Almacén/Buffer global de las instrucciones resultantes.
  └── /ui               
       └── visualizador_tac.php # Archivo de ingesta visual en FRONTEND (HTML, JS, CSS).
```

---

## 4. NODOS AST SOPORTADOS

El sistema obliga un diseño del AST validado a un Diccionario Base o Objeto tipado, el cual garantiza que estos son un **Grafo Dirigido Acíclico (DAG)**. 

Listado oficial de nodos a interpretar en el generador:

*   **Raíz y Sub-rutinas:** `Program`, `Block`.
*   **Asignaciones y Estructuras:** `Assignment`, `Variable`, `Constant`.
*   **Operacionales y Matemáticos:** `BinaryOp` (+, -, *, /, mod), `UnaryOp` (not).
*   **Relacionales y Lógicas:** `RelationalOp` (<, >, <=, etc.), `LogicalOp` (and, or).
*   **Control de Flujo Base:** `If` (Simple), `IfElse` (Doble).
*   **Iteracionales:** `While` (Indefinido), `For` (Acotado explícitamente).
*   **Gestión Paramétrica:** `FunctionCall` o `ProcedureCall`.

Para más detalles referirse al archivo contiguo: `AST_CONTRACT.md`.

---

## 5. REGLAS DE GENERACIÓN TAC

El recorrido de generación sobre la anatomía del árbol sigue este motor de directrices irrefutables:

1.  **Iteración Ascendente Postorden (Bottom-Up DFS):** Cuando ingresan los nodos al bloque del Walker, las evaluaciones compuestas y relacionales deben obligar la recursividad hasta llegar puramente a sus nodos Hoja (Variables / Constantes). Su retorno debe burbujear de regreso para emitir la evaluación atómica hacia una asignación interina TAC.
2.  **Tratamiento Restrictivo (Formatividad Simple):** Todo compendio de cálculo se restringe forzosamente dentro del formato `(variable = op1 operator op2)`.
3.  **Lógica Salto Negada (`IfZ`):** Modos restrictivos de iteración (Condicionales e Interpolaciones de Bucle) requerirán la emisión en TAC usando el desvío condicional a *Zero* en ensamble abstracto, ej: `IfZ t1 Goto L2`.
4.  **Desvío Absoluto (`Goto`):** Terminado el cuerpo de bloque verídico (If) o la interrupción natural de cada anillo iterativo (While / For) saltará incondicionalmente sin variables. Todo `Goto` debe apuntar hacia una rúbrica temporal referenciada por un **Label (LX)**.

Para la matriz de transformación referirse a: `Tabla_Transformacion_AST_TAC.md`.

---

## 6. COMPONENTES PRINCIPALES DEL SISTEMA

*   **`TACGenerator`:** Controlador principal expuesto. Toma el script parseado (o su AST nativo serializado) y comanda al `Walker`. Expone el string crudo multilínea que solicita el cliente.
*   **`ASTWalker`:** Separa la responsabilidad interna al momento de iterar la profundidad en árbol de su parámetro emisor:
    *   *Expressions (`generateExpression`)*: Siempre retorna R-Values limpios, llamando internamente a `TempManager`. (Emiten instrucc. y proveen datos).
    *   *Statements (`generateStatement`)*: Solo recorre acciones modulares complejas (If, bucles), llama al `LabelManager` para el anidamiento lógico. No devuelve valores recursivos.
*   **`TempManager` & `LabelManager` (Singletons lógicos):** Salvaguardan sus constructos indexados para nunca chocar memorias. (`t` seguido de int numérico, iterando auto-incremental. Del mismo modo con la letra `L` para tags).
*   **`InstructionList`:** El Buffer inyector global que no conoce comportamiento de árbol, solo adosa líneas o strings TAC que el Walker le escupe.

---

## 7. REGLAS DE DESARROLLO PARA AGENTES

Para cualquier agente y desarrollador interviniente en la iteración, mantenimiento o diagnóstico de este sistema TAC, seguir con máxima prevalencia y estricto compromiso:

*   **REGLA 1 - Invarianza Tri-direccional:** Nunca introducir refactorizaciones lógicas del algoritmo de recorrido que superen el límite sintáctico de los **3 operandos TAC**. Generar una instrucción del tipo `t1 = a + b * c` es un fallo fatal. Forzar fragmentación.
*   **REGLA 2 - Dependencia y Solidificidad:** Un módulo **nunca** debe saltar la estructura base y responsabilizarse de otra cosa externa. (Ej: El manejador del Parser/Scanner no tiene contexto de lo que el LabelManager escupa, es ajeno. El Generador TAC es enteramente abstraído, por lo tanto no verifica validación de variables "no inicializadas". De serlo, el Semantic Analyzer falló su rol anterior).
*   **REGLA 3 - Integridad y Respeto Absoluto al AST:** Acata en todo momento la naturaleza inmutable del `AST_CONTRACT.md`. Trabajar enteramente con el nodo `type` para inferir flujos lógicos y asumir que todas las hojas proveerán el campo `value` o `name`.
*   **REGLA 4 - Generación de Memoria Unívoca:** *Prohibición implacable de inyectar variables en duro* a los sub-strings del flujo. Si se requiere una asignación nueva, se pedirá obligatoriamente una instancia abstracta al catálogo de `TempManager::newTemp()` o `LabelManager::newLabel()`. Desafiar esto romperá desvíos incondicionales y mutará memoria reservada.
