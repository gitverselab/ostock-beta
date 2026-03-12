<?php
/**
 * Generates a SKU string based on:
 *   - $name      : product name
 *   - $variant   : product variant
 *   - $criteria  : additional criteria or category
 *   - $itemNum   : numerical item identifier
 *
 * Current pattern: 3 letters from name, 2 letters from variant,
 * 2 letters from criteria, and a 4-digit zero-padded item number.
 */
function generate_sku(string $name, string $variant, string $criteria, int $itemNum): string {
    // 1) Remove non-alphanumeric characters and convert to uppercase
    $cleanName     = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($name));
    $cleanVariant  = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($variant));
    $cleanCriteria = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($criteria));

    // 2) Take substrings of desired lengths (using str_pad if too short)
    $partName     = str_pad(substr($cleanName, 0, 3), 3, 'X');     // e.g. "MEK" or "MEK" → "MEK", if "<3", pad with "X"
    $partVariant  = str_pad(substr($cleanVariant, 0, 2), 2, 'X');  // e.g. "RS" or pad "R" → "RX"
    $partCriteria = str_pad(substr($cleanCriteria, 0, 2), 2, 'X');

    // 3) Zero-pad the item number to 4 digits
    $partItemNum = str_pad((string) $itemNum, 4, '0', STR_PAD_LEFT); // e.g. 7 → "0007", 123 → "0123"

    // 4) Assemble and return
    return "{$partName}-{$partVariant}-{$partCriteria}-{$partItemNum}";
}

// If form was submitted, process and show the generated SKU
$generatedSku = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve raw POST inputs
    $nameInput     = $_POST['name'] ?? '';
    $variantInput  = $_POST['variant'] ?? '';
    $criteriaInput = $_POST['criteria'] ?? '';
    $itemNumberRaw = $_POST['item_number'] ?? '';

    // Basic validation: ensure item number is integer
    if (is_numeric($itemNumberRaw)) {
        $itemNumber = (int) $itemNumberRaw;
        $generatedSku = generate_sku($nameInput, $variantInput, $criteriaInput, $itemNumber);
    } else {
        $generatedSku = 'ERROR: Item Number must be numeric.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SKU Generator</title>
  <style>
    body {
      font-family: sans-serif;
      max-width: 480px;
      margin: 40px auto;
      line-height: 1.5;
    }
    label {
      display: block;
      margin-top: 1em;
    }
    input[type="text"],
    input[type="number"] {
      width: 100%;
      padding: 0.5em;
      box-sizing: border-box;
    }
    button {
      margin-top: 1em;
      padding: 0.5em 1em;
      font-size: 1em;
    }
    .result {
      margin-top: 1.5em;
      padding: 1em;
      background: #f3f3f3;
      border: 1px solid #ccc;
      word-break: break-all;
    }
    .error {
      color: #b00;
    }
  </style>
</head>
<body>
  <h2>SKU Generator</h2>
  <form method="post" action="">
    <label>
      Product Name:<br />
      <input type="text" name="name" required placeholder="e.g. Mechanical Keyboard" />
    </label>

    <label>
      Variant:<br />
      <input type="text" name="variant" required placeholder="e.g. Red Switch" />
    </label>

    <label>
      Criteria:<br />
      <input type="text" name="criteria" required placeholder="e.g. Wireless" />
    </label>

    <label>
      Item Number:<br />
      <input type="number" name="item_number" required min="0" placeholder="e.g. 123" />
    </label>

    <button type="submit">Generate SKU</button>
  </form>

  <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <div class="result">
      <?php if (strpos($generatedSku, 'ERROR') === 0): ?>
        <span class="error"><?= htmlspecialchars($generatedSku) ?></span>
      <?php else: ?>
        <strong>Generated SKU:</strong> <?= htmlspecialchars($generatedSku) ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</body>
</html>
