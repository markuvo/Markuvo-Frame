<?php

namespace Tests\Unit;

use Core\Database\DB;
use Core\System\ValidationException;
use Core\System\Validator;
use Tests\TestCase;

class ValidatorTest extends TestCase
{
    protected function errors(array $data, array $rules, array $messages = []): array
    {
        return Validator::make($data, $rules, $messages)->errors();
    }

    public function testRequiredAndEmptyRulesSkip(): void
    {
        $e = $this->errors(['a' => '', 'b' => '  ', 'c' => null], ['a' => 'required', 'b' => 'required', 'c' => 'required|email', 'd' => 'email']);

        $this->assertSame(['a', 'b', 'c'], array_keys($e));
        $this->assertCount(1, $e['c'], 'required başarısızsa diğer kurallar denenmez');
    }

    public function testMinMaxUseLengthForStringsAndValueForNumeric(): void
    {
        $this->assertSame([], $this->errors(['s' => 'abcdef'], ['s' => 'min:6|max:6']));
        $this->assertArrayHasKey('s', $this->errors(['s' => '123'], ['s' => 'min:6']), 'rakam gibi metin de karakter sayılır');
        $this->assertSame([], $this->errors(['n' => '25'], ['n' => 'numeric|min:18|max:30']));
        $this->assertArrayHasKey('n', $this->errors(['n' => '10'], ['n' => 'numeric|min:18']));
        $this->assertSame([], $this->errors(['t' => 'çğı'], ['t' => 'min:3|max:3']), 'çok baytlı karakterler doğru sayılır');
    }

    public function testCommonRules(): void
    {
        $ok = [
            'e' => 'ali@site.com', 'u' => 'https://markuvo.com', 'i' => '12', 'b' => '1', 'd' => '2026-10-24',
            'in' => 'a', 'al' => 'Şükrü', 'an' => 'abc123', 'r' => 'AB12',
        ];
        $rules = [
            'e' => 'email', 'u' => 'url', 'i' => 'integer', 'b' => 'boolean', 'd' => 'date',
            'in' => 'in:a,b', 'al' => 'alpha', 'an' => 'alpha_num', 'r' => 'regex:/^[A-Z]{2}\d{2}$/',
        ];

        $this->assertSame([], $this->errors($ok, $rules));
        $this->assertCount(9, $this->errors(
            ['e' => 'x', 'u' => 'x', 'i' => '1.5', 'b' => 'belki', 'd' => 'dün', 'in' => 'z', 'al' => 'a1', 'an' => 'a b', 'r' => 'xx'],
            $rules
        ));
    }

    public function testConfirmedSameAndNullable(): void
    {
        $this->assertSame([], $this->errors(['p' => 'abc', 'p_confirmation' => 'abc'], ['p' => 'confirmed']));
        $this->assertArrayHasKey('p', $this->errors(['p' => 'abc', 'p_confirmation' => 'abd'], ['p' => 'confirmed']));
        $this->assertSame([], $this->errors(['x' => null], ['x' => 'nullable|email']));
        $this->assertSame([], $this->errors(['a' => '1', 'b' => '1'], ['b' => 'same:a']));
    }

    public function testArrayRulesAndNestedKeys(): void
    {
        $e = $this->errors(['user' => ['mail' => 'x']], ['user.mail' => ['required', 'email']]);

        $this->assertArrayHasKey('user.mail', $e);
    }

    public function testUniqueAndExistsAgainstDatabase(): void
    {
        DB::statement('CREATE TABLE members (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)');
        DB::table('members')->insert(['email' => 'var@site.com']);

        $this->assertArrayHasKey('email', $this->errors(['email' => 'var@site.com'], ['email' => 'unique:members,email']));
        $this->assertSame([], $this->errors(['email' => 'yeni@site.com'], ['email' => 'unique:members,email']));
        $this->assertSame([], $this->errors(['email' => 'var@site.com'], ['email' => 'unique:members,email,1']), 'kendi kaydı hariç tutulabilir');
        $this->assertSame([], $this->errors(['email' => 'var@site.com'], ['email' => 'exists:members,email']));
        $this->assertArrayHasKey('email', $this->errors(['email' => 'yok@site.com'], ['email' => 'exists:members,email']));
    }

    public function testUniqueRejectsMaliciousTableName(): void
    {
        $this->expectException(\Throwable::class);
        $this->errors(['x' => '1'], ['x' => 'unique:members; DROP TABLE members,email']);
    }

    public function testCustomAndTurkishMessages(): void
    {
        $e = $this->errors(['email' => ''], ['email' => 'required'], ['email.required' => 'Lütfen :attribute girin.']);
        $this->assertSame(['Lütfen email girin.'], $e['email']);

        $e = $this->errors(['ad' => 'a'], ['ad' => 'min:3']);
        $this->assertSame('ad en az 3 karakter olmalıdır.', $e['ad'][0]);
    }

    public function testValidateReturnsOnlyRuleFieldsAndThrows(): void
    {
        $data = Validator::make(['a' => '1', 'b' => '2', '_token' => 'x'], ['a' => 'required'])->validate();
        $this->assertSame(['a' => '1'], $data);

        try {
            Validator::make(['a' => ''], ['a' => 'required'])->validate();
            $this->fail('ValidationException bekleniyordu');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('a', $e->errors());
            $this->assertSame(['a' => ''], $e->input());
        }
    }

    public function testUnknownRuleIsAnError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->errors(['a' => 'x'], ['a' => 'olmayankural']);
    }
}
