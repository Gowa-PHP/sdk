# Integration example

`check-device.php` verifies this SDK against an existing GOWA endpoint by reading one known device. It does not send messages, create devices, or otherwise alter GOWA state. `send-text.php` sends one explicit text-message integration test.

## Setup

Install dependencies from the repository root, then create a local credentials file:

```bash
composer install
cp examples/.env.example examples/.env
```

Edit `examples/.env` with a real endpoint, credentials, and the ID of an existing device. This file is ignored by Git; never commit credentials.

Load the variables and run the check from a Bash-compatible shell:

```bash
set -a
source examples/.env
set +a
php examples/check-device.php
```

The script exits with code `0` when the device is found, `2` when the endpoint responds but the device does not exist, and `1` for missing configuration or connection/authentication errors. Use a non-production device and account whenever possible.

## Send a text message

When run interactively, the script asks for a consented test recipient in international format (for example, `5511999999999`) and the message text. It then requires you to type `SEND` before sending. For automation, set `GOWA_RECIPIENT`, `GOWA_TEST_MESSAGE`, and `GOWA_SEND_MESSAGE=1` in the environment; this bypasses the prompts and confirmation.

After loading `examples/.env` as above, run:

```bash
php examples/send-text.php
```

It prints the `SentMessage` response when GOWA accepts the request. This operation sends a real WhatsApp message; use only an authorized test account and recipient.
