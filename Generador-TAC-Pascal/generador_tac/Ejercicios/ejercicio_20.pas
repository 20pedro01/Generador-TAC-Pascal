program Ejercicio20;
var charge: integer;
    special_ready: boolean;
begin
  charge := 0;
  special_ready := false;
  while not special_ready do
  begin
    charge := charge + 15;
    if charge >= 100 then
      special_ready := true
    else
      charge := charge + 5;
  end;
  writeln('¡Habilidad especial lista!');
end.