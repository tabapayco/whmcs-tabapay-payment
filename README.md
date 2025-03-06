# Tabapay Payment Gateway for WHMCS

This module integrates the Tabapay payment gateway into WHMCS, enabling clients to pay invoices using the Tabapay service.

## Installation

1. **Upload the File**  
   Upload `tabapay.php` to the `/modules/gateways/` directory of your WHMCS installation.

2. **Activate the Gateway**  
   - Log in to your WHMCS admin panel.  
   - Navigate to: `https://your-site.com/whmcs/admin/index.php?rp=/admin/apps/browse/payments`.  
   - Search for "Tabapay" in the payment gateways section and activate it.

## Configuration

After activation, configure the gateway settings in WHMCS:  
- **Friendly Name**: Displayed as "تاباپی" (Tabapay in Persian).  
- **Currency Type**: Choose between "ریال" (IRR) or "تومان" (IRT).  
- **Merchant ID**: Enter the Merchant Code provided by Tabapay.  
- **Test Mode**: Enable this option (tick "Yes") to use the sandbox environment for testing.

## How It Works

1. **Payment Initiation**:  
   - When a client selects the Tabapay gateway, they are redirected to the Tabapay payment page.  
   - The module sends invoice details (amount, description, email, etc.) to the Tabapay API.

2. **Callback Handling**:  
   - After payment, Tabapay redirects the client back to your WHMCS site with a token and status.  
   - The module verifies the transaction and updates the invoice status (paid or failed).  

3. **Transaction Logging**:  
   - Successful and failed transactions are logged in WHMCS for tracking purposes.

## Requirements

- WHMCS 7.x or higher.  
- A valid Tabapay Merchant ID.  
- cURL enabled on your server for API communication.

## API Endpoints

- **Live Mode**: `https://api.tabapay.ir/v1/create` and `https://api.tabapay.ir/v1/verify`  
- **Test Mode**: `https://api.tabapay.ir/v1/sandbox/create` and `https://api.tabapay.ir/v1/sandbox/verify`

## Notes

- Ensure your WHMCS installation has proper permissions to write logs and update invoices.  
- Test the gateway in sandbox mode before enabling it for live payments.  
- The module assumes the `CreateTransaction` and `VerifyTransaction` functions interact with the Tabapay API correctly—verify these with your Tabapay documentation.

## Support

For issues or customizations, contact your developer or refer to the Tabapay API documentation.

---

Stable branch does not included " Auto Verify " functions, see auto-verify branch instead.

