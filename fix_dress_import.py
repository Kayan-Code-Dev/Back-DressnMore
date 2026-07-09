
import sys
path = '/var/www/back-dressnmore-new/app/Services/Tenant/InvoiceService.php'
with open(path, 'r') as f:
    lines = f.readlines()

# Find InvoicePayment line and add Dress after it
new_lines = []
added = False
for line in lines:
    new_lines.append(line)
    if 'use App\Models\Tenant\InvoicePayment;' in line and not added:
        new_lines.append('use App\Models\Tenant\Dress;\n')
        added = True

with open(path, 'w') as f:
    f.writelines(new_lines)

print('Added Dress import' if added else 'Already had it or not found')

# Verify
with open(path, 'r') as f:
    for i, line in enumerate(f, 1):
        if line.startswith('use '):
            print(f'Line {i}: {line.strip()}')
