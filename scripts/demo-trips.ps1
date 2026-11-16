# Demo helper: makes a driver's open trip "on the road" and sends fake GPS points.
# Usage (PowerShell, from the repo root):
#   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#   . .\scripts\demo-trips.ps1
#   Start-DemoTrip -Email "maria@freightpath.test" -Lat 14.1694 -Lng 121.2430 -RestMinutes 20   # Resting
#   Start-DemoTrip -Email "pedro@freightpath.test" -Lat 13.9411 -Lng 121.1631 -MinutesAgo 15    # No signal
# Needs: Laravel running on http://127.0.0.1:8000 and a pending trip for that driver.

function Start-DemoTrip {
  param([string]$Email, [double]$Lat, [double]$Lng, [int]$RestMinutes = 0, [int]$MinutesAgo = 0)

  $base = "http://127.0.0.1:8000/api"
  $login = @{ email = $Email; password = "Driver12345"; device_name = "demo" } | ConvertTo-Json
  $r = Invoke-RestMethod "$base/login" -Method Post -Body $login -ContentType "application/json" -Headers @{ Accept = "application/json" }
  $h = @{ Accept = "application/json"; Authorization = "Bearer $($r.token)" }

  $trip = (Invoke-RestMethod "$base/trips" -Headers $h).data | Where-Object { $_.status -in "pending","accepted","en_route" } | Select-Object -First 1
  if (-not $trip) { Write-Host "$Email has no open trip"; return }

  foreach ($s in "accepted","en_route") {
    $needed = ($s -eq "accepted" -and $trip.status -eq "pending") -or ($s -eq "en_route" -and $trip.status -ne "en_route")
    if ($needed) {
      Invoke-RestMethod "$base/trips/$($trip.id)/status" -Method Post -Headers $h -Body (@{ status = $s } | ConvertTo-Json) -ContentType "application/json" | Out-Null
    }
  }

  # Driving towards ($Lat, $Lng), then standing still for $RestMinutes.
  $points = @()
  for ($i = 0; $i -le 24; $i++) {
    $t = $i / 24
    $points += @{ lat = ($Lat - 0.05) + 0.05 * $t; lng = ($Lng - 0.05) + 0.05 * $t }
  }
  for ($i = 1; $i -le ($RestMinutes * 2); $i++) {
    $points += @{ lat = $Lat + (($i % 2) * 0.0002 - 0.0001); lng = $Lng }
  }

  $now = (Get-Date).ToUniversalTime().AddMinutes(-$MinutesAgo)
  $fmt = "yyyy-MM-dd'T'HH:mm:ss'Z'"
  $n = $points.Count
  $pings = for ($i = 0; $i -lt $n; $i++) {
    @{ lat = $points[$i].lat; lng = $points[$i].lng; accuracy = 10; recorded_at = $now.AddSeconds(-30 * ($n - 1 - $i)).ToString($fmt) }
  }

  $payload = @{ schedule_id = $trip.id; pings = @($pings) } | ConvertTo-Json -Depth 5
  Invoke-RestMethod "$base/locations" -Method Post -Headers $h -Body $payload -ContentType "application/json"
}