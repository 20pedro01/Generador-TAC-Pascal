program Ejercicio19;
var energy, attacks: integer;
begin
  energy := 80;
  attacks := 0;
  while energy > 0 do
  begin
    if attacks >= 2 then
      energy := energy + 10;
    energy := energy - 15;
    attacks := attacks + 1;
  end;
  writeln('Energía agotada!');
end.