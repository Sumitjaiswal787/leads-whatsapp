# PowerShell script to prepare Hostinger deployment bundle
$destZip = "deployment.zip"
$excludeList = @("backend", "node_modules", ".git", ".gemini", "deployment.zip", "prepare_deploy.ps1", "debug_backend.php", "scratch", "database/schema.sql")

if (Test-Path $destZip) { Remove-Item $destZip }

Write-Host "Creating deployment bundle..." -ForegroundColor Cyan

# Get all items except the excluded ones
$itemsToInclude = Get-ChildItem -Path "." -Exclude $excludeList

# Since the -Exclude flag on Get-ChildItem is shallow (it only excludes top-level items),
# we filter them out cleanly:
$filteredItems = $itemsToInclude | Where-Object { $excludeList -notcontains $_.Name }

Compress-Archive -Path $filteredItems.FullName -DestinationPath $destZip -Update

Write-Host "SUCCESS: $destZip created!" -ForegroundColor Green
Write-Host "Now upload this file to your Hostinger public_html folder." -ForegroundColor Yellow
