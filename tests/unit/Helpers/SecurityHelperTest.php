<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Exceptions\BadRequestException;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Security Helper Tests
 */
class SecurityHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('security');
    }

    // ==================== sanitize_filename() TESTS ====================

    public function testSanitizeFilenameAllowsValidFilename(): void
    {
        $result = sanitize_filename('my-document.pdf');

        $this->assertEquals('my-document.pdf', $result);
    }

    public function testSanitizeFilenameAllowsImageFiles(): void
    {
        $filenames = ['photo.jpg', 'image.png', 'graphic.gif', 'icon.svg'];

        foreach ($filenames as $filename) {
            $result = sanitize_filename($filename);
            $this->assertEquals($filename, $result);
        }
    }

    public function testSanitizeFilenameBlocksPathTraversalWithDoubleDots(): void
    {
        $this->expectException(BadRequestException::class);

        sanitize_filename('../etc/passwd');
    }

    public function testSanitizeFilenameBlocksPathTraversalInMiddle(): void
    {
        $this->expectException(BadRequestException::class);

        sanitize_filename('uploads/../../../etc/passwd');
    }

    public function testSanitizeFilenameBlocksBackslashPathTraversal(): void
    {
        $this->expectException(BadRequestException::class);

        sanitize_filename('..\\windows\\system32\\config');
    }

    public function testSanitizeFilenameBlocksDirectorySeparator(): void
    {
        $this->expectException(BadRequestException::class);

        sanitize_filename('subdir/file.txt');
    }

    public function testSanitizeFilenameBlocksAbsolutePath(): void
    {
        $this->expectException(BadRequestException::class);

        sanitize_filename('/etc/passwd');
    }

    public function testSanitizeFilenameBlocksPhpExtension(): void
    {
        $this->expectException(BadRequestException::class);

        sanitize_filename('malicious.php');
    }

    public function testSanitizeFilenameBlocksPharExtension(): void
    {
        $this->expectException(BadRequestException::class);

        sanitize_filename('exploit.phar');
    }

    public function testSanitizeFilenameBlocksPhtmlExtension(): void
    {
        $this->expectException(BadRequestException::class);

        sanitize_filename('backdoor.phtml');
    }

    public function testSanitizeFilenameBlocksShellScripts(): void
    {
        $dangerousFiles = ['script.sh', 'run.bat', 'cmd.exe', 'program.com'];

        foreach ($dangerousFiles as $filename) {
            try {
                sanitize_filename($filename);
                $this->fail("Expected BadRequestException for {$filename}");
            } catch (BadRequestException $e) {
                $this->assertEquals('Invalid file type', $e->getMessage());
            }
        }
    }

    public function testSanitizeFilenameBlocksCaseVariationsOfDangerousExtensions(): void
    {
        $this->expectException(BadRequestException::class);

        sanitize_filename('hack.PHP');
    }

    public function testSanitizeFilenameReplacesSpecialCharacters(): void
    {
        $result = sanitize_filename('file name with spaces.txt');

        $this->assertEquals('file_name_with_spaces.txt', $result);
    }

    public function testSanitizeFilenameBlocksConsecutiveDotsForSecurity(): void
    {
        // Consecutive dots are blocked as they can be used for path traversal obfuscation
        $this->expectException(BadRequestException::class);

        sanitize_filename('file...txt');
    }

    public function testSanitizeFilenameBlocksLeadingDots(): void
    {
        // Leading dots can indicate hidden files or path traversal
        $this->expectException(BadRequestException::class);

        sanitize_filename('...file.txt');
    }

    public function testSanitizeFilenameAllowsSingleLeadingDot(): void
    {
        // Single leading dot for hidden files should be trimmed to prevent confusion
        $result = sanitize_filename('.htaccess');

        $this->assertEquals('htaccess', $result);
    }

    public function testSanitizeFilenameAllowsUnderscoresAndDashes(): void
    {
        $result = sanitize_filename('my_file-name.pdf');

        $this->assertEquals('my_file-name.pdf', $result);
    }

    public function testSanitizeFilenameAllowsAlphanumeric(): void
    {
        $result = sanitize_filename('Document123.txt');

        $this->assertEquals('Document123.txt', $result);
    }

    public function testSanitizeFilenameWithRelativePathAllowsSlashes(): void
    {
        $result = sanitize_filename('uploads/user/file.txt', true);

        $this->assertEquals('uploads/user/file.txt', $result);
    }

    public function testSanitizeFilenameWithRelativePathStillNormalizesBackslashes(): void
    {
        $result = sanitize_filename('uploads\\user\\file.txt', true);

        $this->assertEquals('uploads/user/file.txt', $result);
    }

    public function testSanitizeFilenameWithRelativePathStillBlocksDangerousExtensions(): void
    {
        $this->expectException(BadRequestException::class);

        sanitize_filename('uploads/script.php', true);
    }

    // ==================== generate_token() TESTS ====================

    public function testGenerateTokenReturnsHexString(): void
    {
        $token = generate_token();

        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    public function testGenerateTokenDefaultsTo64Characters(): void
    {
        $token = generate_token();

        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testGenerateTokenAcceptsCustomLength(): void
    {
        $token = generate_token(16);

        $this->assertEquals(32, strlen($token)); // 16 bytes = 32 hex chars
    }

    public function testGenerateTokenReturnsUniqueValues(): void
    {
        $token1 = generate_token();
        $token2 = generate_token();

        $this->assertNotEquals($token1, $token2);
    }

    // ==================== hash_password() TESTS ====================

    public function testHashPasswordReturnsHash(): void
    {
        $hash = hash_password('MyPassword123!');

        $this->assertNotEquals('MyPassword123!', $hash);
        $this->assertStringStartsWith('$2y$', $hash); // bcrypt identifier
    }

    public function testHashPasswordVerifiesCorrectly(): void
    {
        $password = 'SecurePass456!';
        $hash = hash_password($password);

        $this->assertTrue(password_verify($password, $hash));
    }

    public function testHashPasswordRejectsDifferentPassword(): void
    {
        $hash = hash_password('CorrectPassword');

        $this->assertFalse(password_verify('WrongPassword', $hash));
    }

    // ==================== constant_time_compare() TESTS ====================

    public function testConstantTimeCompareReturnsTrueForEqualStrings(): void
    {
        $result = constant_time_compare('secret123', 'secret123');

        $this->assertTrue($result);
    }

    public function testConstantTimeCompareReturnsFalseForDifferentStrings(): void
    {
        $result = constant_time_compare('secret123', 'secret456');

        $this->assertFalse($result);
    }

    public function testConstantTimeCompareIsCaseSensitive(): void
    {
        $result = constant_time_compare('Secret', 'secret');

        $this->assertFalse($result);
    }

    public function testConstantTimeCompareHandlesEmptyStrings(): void
    {
        $this->assertTrue(constant_time_compare('', ''));
        $this->assertFalse(constant_time_compare('value', ''));
        $this->assertFalse(constant_time_compare('', 'value'));
    }
}
