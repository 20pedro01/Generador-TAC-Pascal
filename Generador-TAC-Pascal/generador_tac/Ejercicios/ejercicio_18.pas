program Ejercicio18;
var num: integer;
function es_par(numero: integer): boolean;
begin
  es_par := (numero mod 2) = 0;
end;
begin
  num := 7;
  if es_par(num) then
    writeln(num + ' es par\n')
  else
    writeln(num + ' es impar\n');
end.