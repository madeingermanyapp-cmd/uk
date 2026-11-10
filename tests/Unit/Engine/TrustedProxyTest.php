<?php
declare(strict_types=1);
namespace Tests\Unit\Engine;
use App\Engine\{Request,TrustedProxy};
use PHPUnit\Framework\TestCase;
final class TrustedProxyTest extends TestCase
{
 private array $server;private ?string $proxies;
 protected function setUp():void{$this->server=$_SERVER;$this->proxies=$_ENV['TRUSTED_PROXIES']??null;$_ENV['TRUSTED_PROXIES']='192.0.2.0/24,2001:db8::/32';}
 protected function tearDown():void{$_SERVER=$this->server;if($this->proxies===null)unset($_ENV['TRUSTED_PROXIES']);else $_ENV['TRUSTED_PROXIES']=$this->proxies;}
 public function testPublicForwardedHeadersCannotSpoofTlsOrClientIp():void{$_SERVER=['REMOTE_ADDR'=>'198.51.100.10','HTTP_X_FORWARDED_PROTO'=>'https','HTTP_X_FORWARDED_FOR'=>'203.0.113.25'];$r=new Request();self::assertFalse($r->isSecure());self::assertSame('198.51.100.10',$r->ip());}
 public function testTrustedProxyUsesNearestUntrustedHop():void{$_SERVER=['REMOTE_ADDR'=>'192.0.2.2','HTTP_X_FORWARDED_PROTO'=>'https','HTTP_X_FORWARDED_FOR'=>'203.0.113.25,198.51.100.10,192.0.2.3'];$r=new Request();self::assertTrue($r->isSecure());self::assertSame('198.51.100.10',$r->ip());}
 public function testIpv6AndMalformedNetworks():void{self::assertTrue(TrustedProxy::contains('2001:db8::12'));self::assertFalse(TrustedProxy::contains('2001:db9::12'));$_ENV['TRUSTED_PROXIES']='invalid,192.0.2.0/99';self::assertFalse(TrustedProxy::contains('192.0.2.2'));}
 public function testNoProxyTrustByDefault():void{$_ENV['TRUSTED_PROXIES']='';self::assertFalse(TrustedProxy::contains('127.0.0.1'));$_SERVER=['HTTPS'=>'on'];self::assertTrue((new Request())->isSecure());}
}
