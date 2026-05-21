<?php

namespace RedisQueue\Tests;

use PHPUnit\Framework\TestCase;
use RedisQueue\Message;

class MessageTest extends TestCase
{
    /** @test */
    public function constructor_accepts_data()
    {
        $msg = new Message(['cmd' => 'write', 'text' => 'hello']);
        $this->assertInstanceOf(Message::class, $msg);
    }

    /** @test */
    public function magic_get_returns_value_by_key()
    {
        $msg = new Message(['cmd' => 'write', 'text' => 'hello']);
        $this->assertEquals('write', $msg->cmd);
        $this->assertEquals('hello', $msg->text);
    }

    /** @test */
    public function magic_get_returns_null_for_missing_key()
    {
        $msg = new Message(['cmd' => 'write']);
        $this->assertNull($msg->nonexistent);
    }

    /** @test */
    public function get_returns_value_by_key()
    {
        $msg = new Message(['cmd' => 'write', 'text' => 'hello']);
        $this->assertEquals('hello', $msg->get('text'));
    }

    /** @test */
    public function get_returns_default_for_missing_key()
    {
        $msg = new Message(['cmd' => 'write']);
        $this->assertNull($msg->get('text'));
        $this->assertEquals('default', $msg->get('text', 'default'));
    }

    /** @test */
    public function get_returns_null_for_null_value()
    {
        $msg = new Message(['cmd' => 'write', 'text' => null]);
        $this->assertNull($msg->get('text'));
    }

    /** @test */
    public function has_returns_true_when_key_exists()
    {
        $msg = new Message(['cmd' => 'write']);
        $this->assertTrue($msg->has('cmd'));
    }

    /** @test */
    public function has_returns_true_for_existing_key_with_null_value()
    {
        $msg = new Message(['cmd' => null]);
        $this->assertTrue($msg->has('cmd'));
    }

    /** @test */
    public function has_returns_false_for_missing_key()
    {
        $msg = new Message(['cmd' => 'write']);
        $this->assertFalse($msg->has('text'));
    }

    /** @test */
    public function magic_isset_works_correctly()
    {
        $msg = new Message(['cmd' => 'write', 'text' => null]);
        $this->assertTrue(isset($msg->cmd));
        $this->assertTrue(isset($msg->text));
        $this->assertFalse(isset($msg->nonexistent));
    }

    /** @test */
    public function toArray_returns_all_data()
    {
        $data = ['cmd' => 'write', 'text' => 'hello'];
        $msg = new Message($data);
        $this->assertEquals($data, $msg->toArray());
    }

    /** @test */
    public function toArray_returns_a_copy()
    {
        $data = ['cmd' => 'write'];
        $msg = new Message($data);
        $this->assertEquals($data, $msg->toArray());

        $data['cmd'] = 'changed';
        $this->assertEquals('write', $msg->cmd);
    }

    /** @test */
    public function empty_message_handles_all_operations()
    {
        $msg = new Message([]);
        $this->assertNull($msg->anything);
        $this->assertFalse($msg->has('anything'));
        $this->assertEquals([], $msg->toArray());
    }

    /** @test */
    public function magic_get_delegates_to_get_method()
    {
        $msg = new Message(['key' => 'value']);
        $this->assertEquals($msg->get('key'), $msg->key);
    }

    /** @test */
    public function magic_isset_delegates_to_has_method()
    {
        $msg = new Message(['key' => 'value']);
        $this->assertEquals($msg->has('key'), isset($msg->key));
        $this->assertEquals($msg->has('nope'), isset($msg->nope));
    }
}
