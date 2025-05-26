<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AbstractTest extends WebTestCase
{
    protected static ?AbstractBrowser $client = null;

    protected static $em = null;

    protected static UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::getClient();
        $this->loadFixtures($this->getFixtures());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::$client = null;
    }

    protected static function getClient(?AbstractBrowser $newClient = null): AbstractBrowser
    {
        if (!self::$client) {
            self::$client = self::createClient();
        }

        // Ensure the kernel is booted
        self::$client->getKernel()->boot();

        return self::$client;
    }

    protected static function getEntityManager()
    {
        return static::$em = self::getContainer()->get('doctrine')->getManager();
    }

    /**
     * List of fixtures for certain test.
     */
    protected function getFixtures(): array
    {
        return [];
    }

    /**
     * Load fixtures before test.
     */
    protected function loadFixtures(array $fixtures = []): void
    {
        $loader = new Loader();

        foreach ($fixtures as $fixture) {
            if (!is_object($fixture)) {
                $fixture = new $fixture();
            }

            $loader->addFixture($fixture);
        }

        $em = self::getEntityManager();
        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures());
    }

    public function assertResponseOk(
        ?Response $response = null,
        ?string $message = null,
        string $type = 'text/html'
    ): void {
        $this->failOnResponseStatusCheck($response, 'isOk', $message, $type);
    }

    public function assertResponseRedirect(
        ?Response $response = null,
        ?string $message = null,
        string $type = 'text/html'
    ): void {
        $this->failOnResponseStatusCheck($response, 'isRedirect', $message, $type);
    }

    public function assertResponseNotFound(
        ?Response $response = null,
        ?string $message = null,
        string $type = 'text/html'
    ): void {
        $this->failOnResponseStatusCheck($response, 'isNotFound', $message, $type);
    }

    public function assertResponseForbidden(
        ?Response $response = null,
        ?string $message = null,
        string $type = 'text/html'
    ): void {
        $this->failOnResponseStatusCheck($response, 'isForbidden', $message, $type);
    }

    public function assertResponseCode(
        int $expectedCode,
        ?Response $response = null,
        ?string $message = null,
        string $type = 'text/html'
    ): void {
        $this->failOnResponseStatusCheck($response, $expectedCode, $message, $type);
    }

    protected function guessErrorMessageFromResponse(Response $response, string $type = 'text/html'): string
    {
        try {
            $crawler = new Crawler();
            $crawler->addContent($response->getContent(), $type);

            if (!count($crawler->filter('title'))) {
                $add = '';
                $content = $response->getContent();

                if ('application/json' === $response->headers->get('Content-Type')) {
                    $data = json_decode($content);
                    if ($data) {
                        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $add = ' FORMATTED';
                    }
                }
                $title = '[' . $response->getStatusCode() . ']' . $add . ' - ' . $content;
            } else {
                $title = $crawler->filter('title')->text();
            }
        } catch (\Exception $e) {
            $title = $e->getMessage();
        }

        return trim($title);
    }

    private function failOnResponseStatusCheck(
        ?Response $response = null,
        $func = null,
        ?string $message = null,
        string $type = 'text/html'
    ): void {
        if (null === $func) {
            $func = 'isOk';
        }

        if (null === $response && self::$client) {
            $response = self::$client->getResponse();
        }

        try {
            if (is_int($func)) {
                $this->assertEquals($func, $response->getStatusCode());
            } else {
                $this->assertTrue($response->{$func}());
            }

            return;
        } catch (\Exception $e) {
            // Ignore the exception and continue to fail the test
        }

        $err = $this->guessErrorMessageFromResponse($response, $type);
        if ($message) {
            $message = rtrim($message, '.') . '. ';
        }

        if (is_int($func)) {
            $template = 'Failed asserting Response status code %s equals %s.';
        } else {
            $template = 'Failed asserting that Response[%s] %s.';
            $func = preg_replace('#([a-z])([A-Z])#', '$1 $2', $func);
        }

        $message .= sprintf($template, $response->getStatusCode(), $func, $err);

        $maxLength = 100;
        if (mb_strlen($err, 'utf-8') < $maxLength) {
            $message .= ' ' . $this->makeErrorOneLine($err);
        } else {
            $message .= ' ' . $this->makeErrorOneLine(mb_substr($err, 0, $maxLength, 'utf-8') . '...');
            $message .= "\n\n" . $err;
        }

        $this->fail($message);
    }

    private function makeErrorOneLine(string $text): string
    {
        return preg_replace('#[\n\r]+#', ' ', $text);
    }
}
