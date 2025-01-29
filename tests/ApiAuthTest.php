<?

namespace App\Tests;

use App\DataFixtures\UserFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiAuthTest extends AbstractTest
{
    public function testGetJwtToken()
    {
        $client = self::getClient();

        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'casualuser@msil.ru',
                'password' => '2486200',
            ])
        );

        $this->assertResponseIsSuccessful();

        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }

    public function testAccessProtectedRouteWithJwtToken()
    {
        $client = self::getClient();

        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'casualuser@msil.ru',
                'password' => '2486200',
            ])
        );

        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);
        $token = $data['token'];

        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());
    }

    public function testAccessProtectedRouteWithoutToken()
    {
        $client = self::getClient();

        $client->request('GET', '/api/v1/users/current');

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * List of fixtures for certain test
     */
    protected function getFixtures(): array
    {
        return [new UserFixtures(self::$passwordHasher)];
    }
}
