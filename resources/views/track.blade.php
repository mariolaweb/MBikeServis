<!doctype html>
<html lang="bs">
<head>
  <meta charset="utf-8">
  <title>Praćenje servisa – {{ $wo->number }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 2rem;}
    .card{border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-bottom:16px;}
    .muted{color:#6b7280; font-size:14px}
    table{width:100%; border-collapse:collapse}
    th,td{padding:8px; border-bottom:1px solid #f3f4f6; text-align:left}
    th{text-transform:uppercase; font-size:12px; letter-spacing:.04em; color:#6b7280}
    .right{text-align:right}
    .badge{display:inline-block; padding:4px 8px; border-radius:999px; background:#eef2ff; color:#3730a3; font-size:12px}
    .banner{background:#fffbeb; border:1px solid #fde68a}
  </style>
</head>
<body>
  <h1>Servis: {{ $wo->number }}</h1>
  <p class="muted">Status: <span class="badge">{{ $wo->status->name ?? $wo->status }}</span></p>

  <div class="card">
    <h3>Oprema</h3>
    <p>{{ $wo->gear?->brand }} {{ $wo->gear?->model }} @if($wo->gear?->serial_number) — SN: {{ $wo->gear->serial_number }} @endif</p>
  </div>

  @if($showing === 'estimate')
    <div class="card banner">
      <strong>ERP ponuda</strong>
      <p class="muted">Ovo je predračun – konačne stavke biće vidljive nakon potvrde servisa.</p>
    </div>
  @endif

  <div class="card">
    <h3>Stavke</h3>
    <table>
      <thead>
        <tr>
          <th>Šifra</th><th>Opis</th><th>Kol.</th><th>Cijena</th><th class="right">Iznos</th>
        </tr>
      </thead>
      <tbody>
      @forelse($items as $r)
        <tr>
          <td>{{ $r['sku'] }}</td>
          <td>{{ $r['name'] }}</td>
          <td>{{ number_format($r['qty'], 2, ',', '.') }}</td>
          <td>{{ number_format($r['unit_price'], 2, ',', '.') }}</td>
          <td class="right">{{ number_format($r['line_total'], 2, ',', '.') }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="muted">Nema stavki za prikaz.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  @if($wo->delivered_at)
    <p class="muted">Bicikl isporučen: {{ $wo->delivered_at->format('d.m.Y.') }}</p>
  @endif
</body>
</html>
