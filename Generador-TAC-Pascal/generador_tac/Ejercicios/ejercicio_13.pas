program Ejercicio13;
var aliens, potencia_laser: integer;
begin
  aliens := 5;
  potencia_laser := 10;
  if aliens < 10 then
    writeln('¡Aparece una nave nodriza!');
  if aliens < 5 then
    writeln('¡Aparece una horda de aliens pequeños!');
  if potencia_laser >= aliens * 2 then
    writeln('¡Has ganado el juego!')
  else
    writeln('Quedan ' + aliens + ' aliens enemigos.');
end.