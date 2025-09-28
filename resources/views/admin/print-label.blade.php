<!doctype html>
<html lang="bs">
<head>
  <meta charset="utf-8">
  <title>Print naljepnica</title>
  <style>
    @page { size: A4; margin: 8mm; }
    * { box-sizing: border-box; }
    body { font-family: system-ui, sans-serif; }
    .sheet { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6mm; }
    .label {
      border: 1px dashed #999;
      border-radius: 4mm;
      padding: 3mm;
      display: flex;
      gap: 3mm;
      align-items: center;
    }
    .qr { width: 30mm; height: 30mm; }
    .txt { font-size: 11pt; line-height: 1.2; }
    .muted { color: #666; font-size: 9pt; }
  </style>
</head>
<body onload="window.print()">
  <div class="sheet">
    @for($i = 0; $i < 2; $i++)
      <div class="label">
        <img src="{{ asset($svgPath) }}" class="qr" alt="QR" />
        <div class="txt">
          <div><strong>Nalog #{{ $wo->number }}</strong></div>
          <div class="muted">{{ $wo->public_track_url }}</div>
        </div>
      </div>
    @endfor
  </div>
</body>
</html>
