program Ejercicio17;
var usd, mxn: real;
function usd_a_mxn(usd: real): real;
begin
  usd_a_mxn := usd * 18.50;
end;
function mxn_a_usd(mxn: real): real;
begin
  mxn_a_usd := mxn / 18.50;
end;
begin
  usd := 100;
  writeln(usd_a_mxn(usd));
  mxn := 10000;
  writeln(mxn_a_usd(mxn));
end.