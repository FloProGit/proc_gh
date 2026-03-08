<?php

declare(strict_types=1);

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AutControllerTest extends WebTestCase
{
    private function createTestUser(string $email, string $password): User
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        assert($em instanceof EntityManagerInterface);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        assert($hasher instanceof UserPasswordHasherInterface);

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);
        $user->setFirstName('John');
        $user->setLastName('Doe');

        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function provideEmptyCredentials(): array
    {
        return [
            'Empty email' => ['', 'password951753654852'],
            'Empty password' => ['user@test.com', ''],
            'Both empty' => ['', ''],
        ];
    }

    #[DataProvider('provideEmptyCredentials')]
    public function testLoginWithEmptyFields(string $email, string $password): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([
                'email' => $email,
                'password' => $password,
            ])
        );

        $data = json_decode((string) $client->getResponse()->getContent(), true);

        $this->assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testBadPassword(): void
    {
        $client = static::createClient();

        $this->createTestUser('user@test.com', 'password951753654852');

        $client->request(
            'POST',
            'api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['email' => 'user@test.com', 'password' => 'password9'])
        );

        $this->assertResponseStatusCodeSame(401);

        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Invalid credentials.', $data['message']);
    }

    public function testBadEmail(): void
    {
        $client = static::createClient();

        $this->createTestUser('user@test.com', 'password951753654852');

        $client->request(
            'POST',
            'api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['email' => 'user2@test1.com', 'password' => 'password951753654852'])
        );

        $this->assertResponseStatusCodeSame(401);

        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Invalid credentials.', $data['message']);
    }

    public function testLoginSuccess(): void
    {
        $client = static::createClient();

        $this->createTestUser('user@test.com', 'password951753654852');

        $client->request(
            'POST',
            'api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['email' => 'user@test.com', 'password' => 'password951753654852'])
        );
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }

    public function testRequestWithoutToken(): void
    {
        $client = static::createClient();
        $this->createTestUser('user@test.com', 'password951753654852');
        $client->request(
            'GET',
            'api/me',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
        );
        $this->assertResponseStatusCodeSame(401);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('JWT Token not found', $data['message']);
    }

    public function testRequestWithExpiredToken(): void
    {
        $client = static::createClient();
        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        assert($jwtManager instanceof Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager);
        $user = $this->createTestUser('user@test.com', 'password951753654852');

        $expiredToken = $jwtManager->createFromPayload($user, [
            'iat' => time() - 7200,  // créé il y a 2h
            'exp' => time() - 3600,  // expiré il y a 1h
        ]);
        $client->request(
            'GET',
            'api/me',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$expiredToken,
            ],
        );

        $this->assertResponseStatusCodeSame(401);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Expired JWT Token', $data['message']);
    }

    public function testRequestWithInvalidToken(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            'api/me',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer jesuisuntokeninvalide',
            ],
        );

        $this->assertResponseStatusCodeSame(401);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Invalid JWT Token', $data['message']);
    }

    public function testRequestMeWithValidToken(): void
    {
        $client = static::createClient();
        $this->createTestUser('user@test.com', 'password951753654852');

        $client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['email' => 'user@test.com', 'password' => 'password951753654852']),
        );
        $token = json_decode((string) $client->getResponse()->getContent(), true)['token'];

        $client->request(
            'GET',
            '/api/me',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        // has key verification
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('email', $data['data']);
        $this->assertArrayHasKey('roles', $data['data']);
        $this->assertArrayHasKey('firstName', $data['data']);
        $this->assertArrayHasKey('lastName', $data['data']);
        // content test
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('user@test.com', $data['data']['email']);
        $this->assertEquals('John', $data['data']['firstName']);
        $this->assertEquals('Doe', $data['data']['lastName']);
        $this->assertContains('ROLE_USER', $data['data']['roles']);
    }
}
