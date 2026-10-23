param(
    [ValidateSet('start', 'stop', 'status')]
    [string]$Action = 'status'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$mysqlRoot = Join-Path $projectRoot '.setup/mysql'
$serverRoot = Join-Path $mysqlRoot 'mysql-8.4.13-winx64'
$serverConfig = Join-Path $mysqlRoot 'my.ini'
$clientConfig = Join-Path $mysqlRoot 'client.ini'
$mysql = Join-Path $serverRoot 'bin/mysql.exe'

if (!(Test-Path -LiteralPath $serverConfig) -or !(Test-Path -LiteralPath $clientConfig)) {
    throw 'Local MySQL is not configured. See docs/MySQL-setup.md.'
}

function Test-MySqlConnection {
    $ErrorActionPreference = 'Continue'
    & $mysql "--defaults-extra-file=$clientConfig" --batch --skip-column-names --execute='SELECT 1' *> $null
    return $LASTEXITCODE -eq 0
}

if ($Action -eq 'start') {
    if (Test-MySqlConnection) {
        Write-Output 'MySQL is already running on 127.0.0.1:3306.'
        exit 0
    }

    Start-Process -FilePath (Join-Path $serverRoot 'bin/mysqld.exe') `
        -ArgumentList "--defaults-file=`"$serverConfig`"" -WindowStyle Hidden

    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        Start-Sleep -Seconds 1
        if (Test-MySqlConnection) {
            Write-Output 'MySQL started on 127.0.0.1:3306.'
            exit 0
        }
    }
    throw "MySQL did not start. Check $mysqlRoot/server.err."
}

if ($Action -eq 'stop') {
    & (Join-Path $serverRoot 'bin/mysqladmin.exe') "--defaults-extra-file=$clientConfig" shutdown
} else {
    & $mysql "--defaults-extra-file=$clientConfig" --batch --skip-column-names `
        --execute='SELECT VERSION() AS server_version, @@port AS port, @@version_comment AS product'
}
exit $LASTEXITCODE
