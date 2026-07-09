
import re

with open('/var/www/back-dressnmore-new/app/Services/Tenant/InvoiceService.php', 'r') as f:
    content = f.read()

# Check if Dress import exists
if 'use App\\Models\\Tenant\\Dress;' not in content:
    # Add after InvoicePayment import
    content = content.replace(
        'use App\\Models\\Tenant\\InvoicePayment;',
        'use App\\Models\\Tenant\\InvoicePayment;
use App\\Models\\Tenant\\Dress;'
    )
    with open('/var/www/back-dressnmore-new/app/Services/Tenant/InvoiceService.php', 'w') as f:
        f.write(content)
    print('FIXED: Added Dress import')
else:
    print('Dress import already exists')

# Verify
with open('/var/www/back-dressnmore-new/app/Services/Tenant/InvoiceService.php', 'r') as f:
    lines = f.readlines()
    for i, line in enumerate(lines[:15], 1):
        if line.startswith('use '):
            print(f"  Line {i}: {line.strip()}")
