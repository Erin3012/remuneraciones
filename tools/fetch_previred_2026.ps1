param(
    [string]$OutputDirectory = "$PSScriptRoot\previred_2026"
)

# Descarga los PDF oficiales. Los nombres pueden cambiar (v1/v2/-1/-2),
# por eso se prueban las variantes conocidas y se informa lo que no exista.
$months = @(
    @{ Number='01'; Name='Enero'; Candidates=@('Indicadores-Previsionales-Previred-Enero-2026.pdf') },
    @{ Number='02'; Name='Febrero'; Candidates=@('Indicadores-Previsionales-Previred-Febrero-2026-2.pdf','Indicadores-Previsionales-Previred-Febrero-2026.pdf') },
    @{ Number='03'; Name='Marzo'; Candidates=@('Indicadores-Previsionales-Previred-Marzo-2026.pdf') },
    @{ Number='04'; Name='Abril'; Candidates=@('Indicadores-Previsionales-Previred-Abril-2026.pdf','Indicadores-Previsionales-Previred-Abril-2026-1.pdf') },
    @{ Number='05'; Name='Mayo'; Candidates=@('Indicadores-Previsionales-Previred-Mayo-2026.pdf') },
    @{ Number='06'; Name='Junio'; Candidates=@('Indicadores-Previsionales-Previred-Junio-2026v2.pdf','Indicadores-Previsionales-Previred-Junio-2026.pdf') },
    @{ Number='07'; Name='Julio'; Candidates=@('Indicadores-Previsionales-Previred-Julio-2026.pdf') },
    @{ Number='08'; Name='Agosto'; Candidates=@('Indicadores-Previsionales-Previred-Agosto-2026-1.pdf','Indicadores-Previsionales-Previred-Agosto-2026.pdf','Indicadores-Previsionales-Previred-Agosto-2026-2.pdf') }
)

New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null
foreach ($month in $months) {
    $downloaded = $false
    foreach ($candidate in $month.Candidates) {
        $url = "https://www.previred.com/wp-content/uploads/2026/$($month.Number)/$candidate"
        $target = Join-Path $OutputDirectory "$($month.Number)-$($month.Name)-2026.pdf"
        try {
            Invoke-WebRequest -Uri $url -OutFile $target -UseBasicParsing -ErrorAction Stop
            $size = (Get-Item $target).Length
            if ($size -gt 10000) { Write-Output "OK $($month.Number) $url ($size bytes)"; $downloaded = $true; break }
        } catch { }
    }
    if (-not $downloaded) { Write-Warning "No se encontró PDF oficial para $($month.Name) 2026" }
}
