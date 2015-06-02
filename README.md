# Bitcoin Helper

This class is designed to facilitate the comminication between a JSON-RPC server.

Usage:

```
require_once('BitCoin.php');

$bitcoin = new BitCoin('rpcuser', 'rpcpassword'); // Default host is 127.0.0.1
$bitcoin->setProtocol(BitCoin::PROTOCOL_SSL);

$info = $bitcoin->callMethod('getinfo'); // or $bitcoin->getinfo();
```
