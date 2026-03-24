# Guía de Implementación: Transformación de AST a TAC (Pascal)

## 1. Introducción

Este documento técnico sirve como **guía y mapa de implementación** para el equipo de desarrollo encargado de construir el generador de Código en Tres Direcciones (TAC) estructurado para el compilador de Pascal.

El objetivo central de esta guía es definir de manera lógica e irrefutable **cómo cada tipo de nodo presente en el Árbol Sintáctico Abstracto (AST)** debe ser traducido y emitido como instrucciones TAC equivalentes.

El generador debe utilizar estrictamente las siguientes herramientas para cumplir con el formato:
*   **Variables Temporales:** (`t1`, `t2`, `t3`, etc.) que actuarán como almacenamiento intermedio para evaluar partes de expresiones.
*   **Etiquetas lógicas:** (`L1`, `L2`, `L3`, etc.) que definirán puntos de salto para el control de flujo.
*   **Instrucciones de tres direcciones:** Formato universal tipo tupla donde ninguna instrucción excede tres operandos y una operación.

---

## 2. Catálogo de Nodos y Transformación a TAC

A continuación se detalla cómo se procesa cada tipo de nodo específico del AST para generar código intermedio. La siguiente taxonomía lista el esquema lógico que el implementador de PHP debe respetar de acuerdo al tipo del elemento parseado:

### 1️⃣ Program
Representa el programa completo, que funje como la raíz del AST original.
*   **Ejemplo Pascal:**
    ```pascal
    program ejemplo;
    begin
       x := 1;
    end.
    ```
*   **Traducción TAC:** No genera código TAC directamente, solamente evalúa su contenido e inicia el recorrido del AST hacia sus bloques internos.

---

### 2️⃣ Block
Representa un bloque de sentencias (típicamente encuadrado entre `begin` y `end`).
*   **Ejemplo Pascal:**
    ```pascal
    begin
       sentencia1;
       sentencia2;
    end
    ```
*   **Traducción TAC:** Simplemente se debe iterar y ejecutar el algoritmo de evaluación invocando cada sentencia hija en orden secuencial.

---

### 3️⃣ Assignment (Asignación)
Nodo de asignación para depositar valores evaluables dentro de identificadores/variables.
*   **Ejemplo Pascal:**
    ```pascal
    x := a + b;
    ```
*   **Traducción TAC:**
    ```text
    t1 = a + b
    x = t1
    ```

---

### 4️⃣ Variable
El uso directo e instanciado de una referencia en operaciones atómicas.
*   **Ejemplo Pascal:**
    ```pascal
    x
    ```
*   **Traducción TAC:** No genera instrucción TAC per se. Al evaluarse, este nodo solamente retorna su propio nombre o lexema (`x`) hacia afuera.

---

### 5️⃣ Constant (Constantes)
Cualquier tipo de primitivos fijos nativos de Pascal, englobando números, lógicos o cadena de texto (strings).
*   **Ejemplo Pascal:**
    ```pascal
    5
    true
    'a'
    ```
*   **Traducción TAC:** No genera instrucción TAC directamente explícita. Solo devuelve su valor (ej. el literal `5`, `1/0` para booleanos, o texto `'a'`).

---

### 6️⃣ Binary Operation (Operaciones binarias)
Nodos matemáticos operables hacia un par, como suma, resta, multiplicación, división y módulo.
*   **Ejemplos Pascal:**
    ```pascal
    a + b
    a - b
    a * b
    a / b
    a mod b
    ```
*   **Traducción TAC esperada:**
    ```text
    t1 = a + b
    ```
*   **Algoritmo Conceptual:**
    ```pascal
    left = generate(node.left)
    right = generate(node.right)
    t = newTemp()
    emit(t = left op right)
    ```

---

### 7️⃣ Relational Operation (Comparaciones)
Comparadores relacionales limitativos, vitales en expresiones analizadas que devuelven resultados booleanos resolubles.
*   **Ejemplos Pascal:**
    ```pascal
    a < b
    a <= b
    a > b
    a >= b
    a = b
    a <> b
    ```
*   **Traducción TAC:**
    ```text
    t1 = a < b
    ```
*(Nota: El motor TAC produce y utiliza este temporal como un valor booleano usable en saltos como `IfZ`).*

---

### 8️⃣ Logical Operation (Operadores lógicos)
Evaluación e intersección de lógicas condicionales.
*   **Ejemplos Pascal:**
    ```pascal
    a and b
    a or b
    ```
*   **Traducción TAC:**
    ```text
    t1 = a and b
    ```

---

### 9️⃣ UnaryOp (Operadores Unarios)
Operaciones que se aplican sobre un solo operando, como negaciones aritméticas o lógicas.
*   **Ejemplo Pascal:**
    ```pascal
    not a
    ```
*   **Traducción TAC:**
    ```text
    t1 = not a
    ```

---

### 🔟 IF
Evaluación condicional que forzará el desvío si no se cumple el requisito interno (cero/negativo).
*   **Ejemplo Pascal:**
    ```pascal
    if a < b then
       x := 1;
    ```
*   **Traducción TAC:**
    ```text
    t1 = a < b
    IfZ t1 goto L1
    x = 1
    L1:
    ```

---

### 11️⃣ IF ELSE
Variación del condicional con ramificación paralela y mutuamente excluyente.
*   **Ejemplo Pascal:**
    ```pascal
    if a < b then
       x := 1
    else
       x := 2;
    ```
*   **Traducción TAC:**
    ```text
    t1 = a < b
    IfZ t1 goto L1
    x = 1
    goto L2
    L1:
    x = 2
    L2:
    ```

---

### 12️⃣ WHILE
Algoritmo cíclico recurrente por desvío incondicional final que requiere de dos etiquetas independientes.
*   **Ejemplo Pascal:**
    ```pascal
    while a < 10 do
       a := a + 1;
    ```
*   **Traducción TAC:**
    ```text
    L1:
    t1 = a < 10
    IfZ t1 goto L2
    t2 = a + 1
    a = t2
    goto L1
    L2:
    ```

---

### 13️⃣ FOR
Estructura iterativa definida mediante un contador. Se mapea mediante control posicional y saltos evaluables simulando un bucle While interno.
*   **Ejemplo Pascal:**
    ```pascal
    for i := 1 to 10 do
       x := x + 1;
    ```
*   **Traducción TAC:**
    ```text
    i = 1
    L1:
    t1 = i <= 10
    IfZ t1 goto L2
    t2 = x + 1
    x = t2
    t3 = i + 1
    i = t3
    goto L1
    L2:
    ```

---

### 14️⃣ Procedure / Function Call
Manejo de saltos en memoria hacia subrutinas declaradas en el código usando la pila de parámetros y el invocador.

*   **Ejemplo Pascal:**
    ```pascal
    x := suma(a,b);
    ```
*   **Traducción TAC:**
    ```text
    PushParam a
    PushParam b
    t1 = Call suma
    x = t1
    ```

*   **Si es llamada a Procedimiento (Sin variable de retorno):**
    ```text
    PushParam a
    Call imprimir
    ```

---

## 5. Reglas Generales de Generación TAC

El equipo de implementación deberá asegurar el cumplimiento de las siguientes directivas bajo estricta rigurosidad en su desarrollo PHP:

1.  **Aislamiento de Operaciones:** Cada operación binaria o unaria compleja *siempre* produce obligatoriamente una nueva variable temporal (Ej: si `z := x + y`, se generará primero `t1 = x + y` y ulteriormente `z = t1`). 
2.  **Evaluación Ascendente:** Para asegurar el respeto lógico-matemático (jerarquías), el algoritmo que navega el árbol (AST) usará un **recorrido postorden**. Esto evalúa a los hijos antes que el padre (izquierdas, derivas, lógicas).
3.  **Encapsulado de Saltos Controlados:** El gestor de Etiquetas debe crear marcas atómicas globales (`LX`) a nivel clase que nunca colisionen bajo otros bloques a la hora de inyectar saltos condicionales.

---

## 6. Ejemplo Completo de Transformación Práctica

Observa detenidamente la compilación nativa en conjunto de un bloque estructurado intermedio, manejando condicionales, bloques aritméticos abstractos y resolución de variables en cascada.

**Representación Pascal (AST Raíz):**
```pascal
if a < b then
   x := a + b * c
else
   x := 0;
```

**Flujo Postorden Mapeado y Compilado a TAC:**

```text
t1 = a < b
IfZ t1 goto L1
t2 = b * c
t3 = a + t2
x = t3
goto L2
L1:
x = 0
L2:
```
