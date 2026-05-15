param(
	[string]$BaseUrl = "https://sentences-bundle.hitokoto.cn",
	[string]$TargetDir = (Join-Path $PSScriptRoot "..\sentences")
)

$ErrorActionPreference = "Stop"
$files = @(
	"version.json",
	"sentences/a.json",
	"sentences/b.json",
	"sentences/c.json",
	"sentences/d.json",
	"sentences/e.json",
	"sentences/f.json",
	"sentences/g.json",
	"sentences/h.json",
	"sentences/i.json",
	"sentences/j.json",
	"sentences/k.json",
	"sentences/l.json"
)

$target = Resolve-Path -LiteralPath $TargetDir
$tempDir = Join-Path ([System.IO.Path]::GetTempPath()) ("hitokoto-sentences-" + [Guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Path $tempDir | Out-Null
function Read-JsonFile([string]$Path) {
	(Get-Content -LiteralPath $Path -Raw -Encoding UTF8) | ConvertFrom-Json
}

try {
	foreach ($file in $files) {
		$name = Split-Path $file -Leaf
		$outFile = Join-Path $tempDir $name
		$url = "$($BaseUrl.TrimEnd('/'))/$file"

		Write-Host "Downloading $url"
		Invoke-WebRequest -UseBasicParsing $url -OutFile $outFile

		try {
			Get-Content -LiteralPath $outFile -Raw -Encoding UTF8 | ConvertFrom-Json | Out-Null
		} catch {
			throw "Invalid JSON downloaded for $file`: $($_.Exception.Message)"
		}
	}

	$version = Read-JsonFile (Join-Path $tempDir "version.json")
	$manifest = [ordered]@{
		bundle_version = $version.bundle_version
		updated_at = $version.updated_at
		categories = [ordered]@{}
		total = 0
	}

	foreach ($downloaded in Get-ChildItem -LiteralPath $tempDir -File) {
		Move-Item -LiteralPath $downloaded.FullName -Destination (Join-Path $target $downloaded.Name) -Force
	}

	foreach ($category in $version.sentences) {
		$key = [string]$category.key
		$path = [string]$category.path
		$fileName = Split-Path $path -Leaf
		$filePath = Join-Path $target $fileName
		$data = Read-JsonFile $filePath
		$count = @($data).Count
		$manifest.categories[$key] = [ordered]@{
			path = $path
			count = $count
			ok = ($count -gt 0)
		}
		$manifest.total += $count
	}

	$manifestPath = Join-Path $target "manifest.json"
	$manifest | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $manifestPath -Encoding UTF8

	Write-Host "Updated sentences bundle to $($version.bundle_version)"
} finally {
	if (Test-Path -LiteralPath $tempDir) {
		Remove-Item -LiteralPath $tempDir -Recurse -Force
	}
}
