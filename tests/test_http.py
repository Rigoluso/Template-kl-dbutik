"""Behavioral checks against the actual NGINX -> WordPress -> DB stack."""
import json
import os
import unittest
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

BASE = os.environ.get('SHOP_TEST_URL', 'http://localhost:18080').rstrip('/')

def request(path, method='GET', data=None, headers=None):
    sent = {'User-Agent': 'ShopContainerVerification/0.1.0', **(headers or {})}
    if data is not None:
        sent['Content-Type'] = 'application/json'
        data = json.dumps(data).encode()
    try:
        with urlopen(Request(BASE + path, data=data, headers=sent, method=method), timeout=20) as response:
            return response.status, response.headers, response.read()
    except HTTPError as response:
        return response.code, response.headers, response.read()
    except URLError as error:
        raise AssertionError(f'Actual NGINX endpoint is not available: {BASE}: {error}') from None

class ContainerHTTPTests(unittest.TestCase):
    def test_health_checks_ready_dynamic_application(self):
        status, headers, body = request('/healthz')
        self.assertEqual(status, 200, body.decode(errors='replace'))
        self.assertEqual(json.loads(body)['status'], 'ok')

    def test_storefront_is_rendered_by_real_application(self):
        status, headers, body = request('/')
        self.assertEqual(status, 200)
        self.assertIn('text/html', headers.get('Content-Type', ''))
        self.assertIn('DEMO', body.decode().upper())
        self.assertEqual(headers.get('X-Content-Type-Options'), 'nosniff')

    def test_products_come_from_woocommerce_database(self):
        status, _, body = request('/wp-json/wc/store/v1/products')
        self.assertEqual(status, 200, body.decode(errors='replace'))
        products = json.loads(body)
        self.assertGreaterEqual(len(products), 2)
        self.assertTrue(all(product['id'] > 0 and product['prices']['currency_code'] == 'SEK' for product in products))

    def test_real_guest_cart_accepts_database_product(self):
        product_id = int(os.environ['SHOP_TEST_PRODUCT_ID'])
        status, headers, _ = request('/wp-json/wc/store/v1/cart')
        self.assertEqual(status, 200)
        nonce = headers.get('Nonce')
        token = headers.get('Cart-Token')
        self.assertTrue(nonce or token, 'The real Store API must issue a nonce or cart token')
        auth = {'Cart-Token': token} if token else {'Nonce': nonce}
        status, _, body = request('/wp-json/wc/store/v1/cart/add-item', 'POST', {'id': product_id, 'quantity': 1}, auth)
        self.assertEqual(status, 201, body.decode(errors='replace'))
        self.assertEqual(json.loads(body)['items'][0]['id'], product_id)
        self.assertEqual(json.loads(body)['items'][0]['quantity'], 1)
        self.assertEqual(json.loads(body)['items'][0]['prices']['price'], '28900')

    def test_unconfigured_store_api_cannot_create_purchase(self):
        status, _, body = request('/wp-json/wc/store/v1/checkout', 'POST', {})
        self.assertEqual(status, 503, body.decode(errors='replace'))
        self.assertEqual(json.loads(body)['code'], 'shop_not_launched')

    def test_private_paths_and_executable_uploads_are_unavailable(self):
        for path in ('/.env', '/.git/config', '/wp-config.php', '/wp-content/uploads/exploit.php', '/server-status'):
            with self.subTest(path=path):
                status, _, body = request(path)
                self.assertIn(status, (403, 404))
                self.assertNotIn(b'DB_PASSWORD', body)

    def test_spoofed_proxy_headers_do_not_change_scheme(self):
        status, _, body = request('/', headers={'X-Forwarded-Proto': 'https', 'X-Forwarded-Host': 'attacker.invalid', 'X-Forwarded-For': '192.0.2.123'})
        self.assertEqual(status, 200)
        self.assertNotIn(b'https://attacker.invalid', body)

    def test_admin_requires_login(self):
        status, _, body = request('/wp-admin/')
        self.assertEqual(status, 200)
        self.assertIn(b'name="log"', body)
        self.assertIn(b'name="pwd"', body)

if __name__ == '__main__':
    unittest.main(verbosity=2)
