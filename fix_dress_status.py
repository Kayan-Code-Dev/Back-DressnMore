
import re

path = '/var/www/back-dressnmore-new/app/Services/Tenant/InvoiceService.php'
with open(path, 'r') as f:
    content = f.read()

# Fix STATUS_RESERVED -> STATUS_RENTED (since that's the status used for allocated dresses)
content = content.replace('Dress::STATUS_RESERVED', "Dress::STATUS_RENTED")

with open(path, 'w') as f:
    f.write(content)

print('Fixed STATUS_RESERVED -> STATUS_RENTED')

# Verify the fix
with open(path, 'r') as f:
    for i, line in enumerate(f, 1):
        if 'STATUS_' in line and 'Dress' in line:
            print(f'Line {i}: {line.strip()}')
