# Use PHP 8.4 for this project (UniServer ships 8.3; XAMPP PATH was 8.2)
$Php84 = "C:\php84\php.exe"
if (-not (Test-Path $Php84)) { throw "PHP 8.4 not found at $Php84" }
& $Php84 @args
