$demoRoot = Split-Path -Parent $PSScriptRoot
& php -S 127.0.0.1:8876 -t $demoRoot (Join-Path $demoRoot 'router.php')
