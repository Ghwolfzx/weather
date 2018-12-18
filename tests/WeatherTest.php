<?php

namespace Ghwolf\Weather\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Mockery\Matcher\AnyArgs;
use Ghwolf\Weather\Exceptions\HttpException;
use Ghwolf\Weather\Exceptions\InvalidArgumentException;
use Ghwolf\Weather\Weather;
use PHPUnit\Framework\TestCase;

class WeatherTest extends TestCase
{
    public function testGetWeather()
    {
        $response = new Response(200, [], '{"success": true}');
        $client = \Mockery::mock(Client::class);
        $client->allows()->get('https://restapi.amap.com/v3/weather/weatherInfo', [
            'query' => [
                'key' => 'mock-key',
                'city' => '北京',
                'output' => 'json',
                'extensions' => 'base',
            ]
        ])->andReturn($response);

        $weather = \Mockery::mock(Weather::class, ['mock-key'])->makePartial();
        $weather->allows()->getHttpClient()->andReturn($client);

        $this->assertSame(["success" => true], $weather->getWeather('北京'));

        $response = new Response(200, [], '<hello>content</hello>');
        $client = \Mockery::mock(Client::class);
        $client->allows()->get('https://restapi.amap.com/v3/weather/weatherInfo', [
            'query' => [
                'key' => 'mock-key',
                'city' => '北京',
                'output' => 'xml',
                'extensions' => 'all',
            ]
        ])->andReturn($response);

        $weather = \Mockery::mock(Weather::class, ['mock-key'])->makePartial();
        $weather->allows()->getHttpClient()->andReturn($client);

        $this->assertSame('<hello>content</hello>', $weather->getWeather('北京', 'forecast', 'xml'));
    }

    public function testGetHttpClient()
    {
        $weather = new Weather('mock-key');

        $this->assertInstanceOf(ClientInterface::class, $weather->getHttpClient());
    }

    public function testSetGuzzleOptions()
    {
        $weather = new Weather('mock-key');

        $this->assertNull($weather->getHttpClient()->getConfig('timeout'));

        $weather->setGuzzleOptions(['timeout' => 5000]);

        $this->assertSame(5000, $weather->getHttpClient()->getConfig('timeout'));
    }

    public function testGetWeatherWithInvalidType()
    {
        $weather = new Weather('004d6c06bde5a4474b5ae3c7399b07d5');

        // 断言会抛出此异常类
        $this->expectException(InvalidArgumentException::class);

        // 断言异常消息为 'Invalid type value(base/all): foo'
        $this->expectExceptionMessage('Invalid type value(live/forecast): foo');

        $weather->getWeather('北京', 'foo');

        $this->fail('Failed to assert getWeather throw exception with invalid argument.');
    }

    public function testGetWeatherWithInvalidFormat()
    {
        $weather = new Weather('004d6c06bde5a4474b5ae3c7399b07d5');

        // 断言会抛出此异常类
        $this->expectException(InvalidArgumentException::class);

        // 断言异常消息为 'Invalid response format: array'
        $this->expectExceptionMessage('Invalid response format: array');

        // 因为支持的格式为 xml/json，所以传入 array 会抛出异常
        $weather->getWeather('深圳', 'base', 'array');

        // 如果没有抛出异常，就会运行到这行，标记当前测试没成功
        $this->fail('Failed to assert getWeather throw exception with invalid argument.');
    }

    public function testGetWeatherWithGuzzleRuntimeException()
    {
        $client = \Mockery::mock(Client::class);
        $client->allows()
            ->get(new AnyArgs())
            ->andThrow(new \Exception('request timeout'));

        $weather = \Mockery::mock(Weather::class, ['mock-key'])->makePartial();
        $weather->allows()->getHttpClient()->andReturn($client);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('request timeout');

        $weather->getWeather('北京');
    }

    public function testGetLiveWeather()
    {
        $weather = \Mockery::mock(Weather::class, ['mock-key'])->makePartial();
        $weather->expects()->getWeather('北京', 'live', 'json')->andReturn(['success' => true]);

        $this->assertSame(['success' => true], $weather->getLiveWeather('北京'));
    }

    public function testGetForecastsWeather()
    {
        // 将 getWeather 接口模拟为返回固定内容，以测试参数传递是否正确
        $weather = \Mockery::mock(Weather::class, ['mock-key'])->makePartial();
        $weather->expects()->getWeather('深圳', 'forecast', 'json')->andReturn(['success' => true]);

        // 断言正确传参并返回
        $this->assertSame(['success' => true], $weather->getForecastsWeather('深圳'));
    }
}
