program Ejercicio12;
var name: string;
    age: integer;
    is_vampire: boolean;
begin
  name := 'Dracula';
  age := 500;
  is_vampire := false;
  if (name = 'Dracula') or (age > 100) then
    is_vampire := true;
  if is_vampire then
    writeln('Warning! Vampire detected!')
  else
    writeln('Phew! No vampires here');
end.