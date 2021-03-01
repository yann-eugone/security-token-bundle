<?php

namespace Yokai\SecurityTokenBundle\Tests\Manager;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Yokai\SecurityTokenBundle\Entity\Token;
use Yokai\SecurityTokenBundle\Event\ConsumeTokenEvent;
use Yokai\SecurityTokenBundle\Event\CreateTokenEvent;
use Yokai\SecurityTokenBundle\Event\TokenAlreadyConsumedEvent;
use Yokai\SecurityTokenBundle\Event\TokenConsumedEvent;
use Yokai\SecurityTokenBundle\Event\TokenCreatedEvent;
use Yokai\SecurityTokenBundle\Event\TokenExpiredEvent;
use Yokai\SecurityTokenBundle\Event\TokenNotFoundEvent;
use Yokai\SecurityTokenBundle\Event\TokenRetrievedEvent;
use Yokai\SecurityTokenBundle\Event\TokenTotallyConsumedEvent;
use Yokai\SecurityTokenBundle\EventDispatcher;
use Yokai\SecurityTokenBundle\Exception\TokenConsumedException;
use Yokai\SecurityTokenBundle\Exception\TokenExpiredException;
use Yokai\SecurityTokenBundle\Exception\TokenNotFoundException;
use Yokai\SecurityTokenBundle\Factory\TokenFactoryInterface;
use Yokai\SecurityTokenBundle\InformationGuesser\InformationGuesserInterface;
use Yokai\SecurityTokenBundle\Manager\TokenManager;
use Yokai\SecurityTokenBundle\Manager\UserManagerInterface;
use Yokai\SecurityTokenBundle\Repository\TokenRepositoryInterface;
use Yokai\SecurityTokenBundle\TokenEvents;

/**
 * @author Yann Eugoné <eugone.yann@gmail.com>
 */
class TokenManagerTest extends TestCase
{
    /**
     * @var TokenFactoryInterface|ObjectProphecy
     */
    private $factory;

    /**
     * @var TokenRepositoryInterface|ObjectProphecy
     */
    private $repository;

    /**
     * @var InformationGuesserInterface|ObjectProphecy
     */
    private $informationGuesser;

    /**
     * @var UserManagerInterface|ObjectProphecy
     */
    private $userManager;

    /**
     * @var EventDispatcherInterface|ObjectProphecy
     */
    private $eventDispatcher;

    protected function setUp()
    {
        $this->factory = $this->prophesize(TokenFactoryInterface::class);
        $this->repository = $this->prophesize(TokenRepositoryInterface::class);
        $this->informationGuesser = $this->prophesize(InformationGuesserInterface::class);
        $this->userManager = $this->prophesize(UserManagerInterface::class);
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
    }

    protected function tearDown()
    {
        unset(
            $this->factory,
            $this->repository,
            $this->informationGuesser,
            $this->userManager,
            $this->eventDispatcher
        );
    }

    protected function manager()
    {
        return new TokenManager(
            $this->factory->reveal(),
            $this->repository->reveal(),
            $this->informationGuesser->reveal(),
            $this->userManager->reveal(),
            new EventDispatcher($this->eventDispatcher->reveal())
        );
    }

    /**
     * @test
     * @expectedException \Yokai\SecurityTokenBundle\Exception\TokenNotFoundException
     */
    public function it_dispatch_not_found_exceptions_on_get_token_from_repository()
    {
        $this->repository->get('unique-token', 'forgot_password')
            ->shouldBeCalledTimes(1)
            ->willThrow(TokenNotFoundException::create('unique-token', 'forgot_password'));

        $notFoundEvent = Argument::allOf(
            Argument::type(TokenNotFoundEvent::class),
            Argument::which('getPurpose', 'forgot_password'),
            Argument::which('getValue', 'unique-token')
        );
        $this->eventDispatcher->dispatch(TokenEvents::TOKEN_NOT_FOUND, $notFoundEvent)
            ->shouldBeCalledTimes(1);

        $this->manager()->get('forgot_password', 'unique-token');
    }

    /**
     * @test
     * @expectedException \Yokai\SecurityTokenBundle\Exception\TokenExpiredException
     */
    public function it_dispatch_expired_exceptions_on_get_token_from_repository()
    {
        $this->repository->get('unique-token', 'forgot_password')
            ->shouldBeCalledTimes(1)
            ->willThrow(TokenExpiredException::create('unique-token', 'forgot_password', new \DateTime()));

        $expiredEvent = Argument::allOf(
            Argument::type(TokenExpiredEvent::class),
            Argument::which('getPurpose', 'forgot_password'),
            Argument::which('getValue', 'unique-token')
        );
        $this->eventDispatcher->dispatch(TokenEvents::TOKEN_EXPIRED, $expiredEvent)
            ->shouldBeCalledTimes(1);

        $this->manager()->get('forgot_password', 'unique-token');
    }

    /**
     * @test
     * @expectedException \Yokai\SecurityTokenBundle\Exception\TokenConsumedException
     */
    public function it_dispatch_used_exceptions_on_get_token_from_repository()
    {
        $this->repository->get('unique-token', 'forgot_password')
            ->shouldBeCalledTimes(1)
            ->willThrow(TokenConsumedException::create('unique-token', 'forgot_password', 3));

        $alreadyConsumedEvent = Argument::allOf(
            Argument::type(TokenAlreadyConsumedEvent::class),
            Argument::which('getPurpose', 'forgot_password'),
            Argument::which('getValue', 'unique-token')
        );
        $this->eventDispatcher->dispatch(TokenEvents::TOKEN_ALREADY_CONSUMED, $alreadyConsumedEvent)
            ->shouldBeCalledTimes(1);

        $this->manager()->get('forgot_password', 'unique-token');
    }

    /**
     * @test
     */
    public function it_get_token_from_repository()
    {
        $this->repository->get('unique-token', 'forgot_password')
            ->shouldBeCalledTimes(1)
            ->willReturn($expected = $this->prophesize(Token::class)->reveal());

        $retrievedEvent = Argument::allOf(
            Argument::type(TokenRetrievedEvent::class),
            Argument::which('getToken', $expected)
        );
        $this->eventDispatcher->dispatch(TokenEvents::TOKEN_RETRIEVED, $retrievedEvent)
            ->shouldBeCalledTimes(1);

        $token = $this->manager()->get('forgot_password', 'unique-token');

        self::assertSame($expected, $token);
    }

    /**
     * @test
     */
    public function it_create_unique_token()
    {
        $expectedToken = new Token(
            'string',
            'jdoe',
            'unique-token-2',
            'forgot_password',
            '+1 day',
            '+1 month',
            1,
            ['payload', 'information'],
            ['created', 'information']
        );

        $this->factory->create('john-doe', 'forgot_password', ['payload', 'information'])
            ->shouldBeCalledTimes(1)
            ->willReturn($expectedToken);

        $this->repository->create($expectedToken)
            ->shouldBeCalledTimes(1);

        $createEvent = Argument::allOf(
            Argument::type(CreateTokenEvent::class),
            Argument::which('getPurpose', 'forgot_password'),
            Argument::which('getUser', 'john-doe'),
            Argument::which('getPayload', ['payload', 'information'])
        );
        $this->eventDispatcher->dispatch(TokenEvents::CREATE_TOKEN, $createEvent)
            ->shouldBeCalledTimes(1);

        $createdEvent = Argument::allOf(
            Argument::type(TokenCreatedEvent::class),
            Argument::which('getToken', $expectedToken)
        );
        $this->eventDispatcher->dispatch(TokenEvents::TOKEN_CREATED, $createdEvent)
            ->shouldBeCalledTimes(1);

        $token = $this->manager()->create('forgot_password', 'john-doe', ['payload', 'information']);

        self::assertSame($expectedToken, $token);
    }

    /**
     * @test
     */
    public function it_consume_token()
    {
        $token = new Token('string', 'jdoe', 'unique-token', 'reset-password', '+1 day', '+1 month');

        $this->informationGuesser->get()
            ->shouldBeCalledTimes(1)
            ->willReturn(['some', 'precious', 'information']);

        $this->repository->update($token)
            ->shouldBeCalledTimes(1);

        $consumeEvent = Argument::allOf(
            Argument::type(ConsumeTokenEvent::class),
            Argument::which('getToken', $token),
            Argument::which('getInformation', ['some', 'precious', 'information'])
        );
        $this->eventDispatcher->dispatch(TokenEvents::CONSUME_TOKEN, $consumeEvent)
            ->shouldBeCalledTimes(1);

        $consumedEvent = Argument::allOf(
            Argument::type(TokenConsumedEvent::class),
            Argument::which('getToken', $token)
        );
        $this->eventDispatcher->dispatch(TokenEvents::TOKEN_CONSUMED, $consumedEvent)
            ->shouldBeCalledTimes(1);

        $totallyConsumedEvent = Argument::allOf(
            Argument::type(TokenTotallyConsumedEvent::class),
            Argument::which('getToken', $token)
        );
        $this->eventDispatcher->dispatch(TokenEvents::TOKEN_TOTALLY_CONSUMED, $totallyConsumedEvent)
            ->shouldBeCalledTimes(1);

        $this->manager()->consume($token);

        self::assertCount(1, $token->getUsages());
        self::assertSame(1, $token->getCountUsages());
        self::assertTrue($token->isConsumed());
        self::assertNotNull($usage = $token->getLastUsage());
        self::assertSame(['some', 'precious', 'information'], $usage->getInformation());
        self::assertInstanceOf(\DateTime::class, $usage->getCreatedAt());
    }

    /**
     * @test
     */
    public function it_extract_user_from_token()
    {
        $token = new Token('string', 'jdoe', 'unique-token', 'reset-password', '+1 day', '+1 month', 1, []);

        $this->userManager->get('string', 'jdoe')
            ->shouldBeCalledTimes(1)
            ->willReturn('john doe');

        $user = $this->manager()->getUser($token);

        self::assertSame('john doe', $user);
    }
}
