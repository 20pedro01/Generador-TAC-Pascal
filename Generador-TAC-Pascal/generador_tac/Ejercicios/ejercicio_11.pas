program Ejercicio11;
var a, b: integer;
begin
  a := 5;
  b := 10;
  if (a * 2 > b) then
    b := b - a
  else
    b := b + a;
end.