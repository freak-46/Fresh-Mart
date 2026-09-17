<?php
session_start();
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Confirmed — Fresh Mart</title>
  <link rel="stylesheet" href="src/output.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
  <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } .font-serif { font-family: 'Playfair Display', serif; } </style>
</head>
<body class="bg-[#FBFBFA] text-slate-800 antialiased min-h-screen flex flex-col items-center justify-center p-6">

  <div class="max-w-md w-full bg-white border border-slate-100 p-8 rounded-[36px] text-center shadow-lg">
    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-6 text-2xl shadow-inner bg-emerald-50 text-emerald-600 rounded-2xl">
      ✓
    </div>
    <h1 class="font-serif text-2xl font-black tracking-tight text-emerald-950">Order Confirmed</h1>
    <p class="mt-2 text-xs font-medium text-slate-400">Your request routing completed successfully.</p>
    
    <div class="p-4 my-6 text-xs font-bold border bg-slate-50 border-slate-100 rounded-2xl text-slate-600">
      Manifest ID Track Trace: <span class="text-emerald-800">#000<?php echo $order_id; ?></span>
    </div>

    <p class="max-w-xs mx-auto text-xs leading-relaxed text-slate-500">
      Our packaging team is assembling your fresh artisanal elements right now.
    </p>

    <a href="index.php" class="inline-block px-6 py-3 mt-8 text-xs font-bold tracking-widest text-white uppercase transition bg-emerald-800 rounded-xl hover:bg-emerald-900">
      Return To Portal
    </a>
  </div>

</body>
</html>