program Ejercicio14;
var shield, hits: integer;
begin
  shield := 100;
  hits := 0;
  while shield > 0 do
  begin
    hits := hits + 1;
    if hits = 3 then
      shield := 50;
    shield := shield - 20;
  end;
  writeln('Escudo destruido!');
end.