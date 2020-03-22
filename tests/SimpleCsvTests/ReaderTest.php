<?php
namespace SimpleCsvTests;

use PHPUnit_Framework_TestCase;
use SimpleCsv\Reader;

/**
 * Class ReaderTest
 * @package SimpleCsvTests
 */
class ReaderTest extends PHPUnit_Framework_TestCase
{
    public function testInvalidFile()
    {
        $this->expectException('\SimpleCsv\Exception\FileDoesNotExistException');
        $reader = new Reader(__DIR__ . '/this/path/does-not-exist.csv');
        $this->assertEquals(0, $reader->getLastLineNumber());
        $reader->openFile();
        $this->assertEquals(0, $reader->getLastLineNumber());
    }

    public function testValidFile()
    {
        $reader = new Reader(__DIR__ . '/../data/mock_data_valid.csv');
        $this->assertEquals(0, $reader->getLastLineNumber());
        $reader->openFile();
        $headers = $reader->readHeaders();
        $this->assertEquals(1, $reader->getLastLineNumber());
        $this->assertNotEmpty($headers);
        $this->assertTrue($reader->closeFile());
    }

    public function testValidFileHeaderMap()
    {
        $reader = new Reader(__DIR__ . '/../data/mock_data_valid.csv');
        $this->assertEquals(0, $reader->getLastLineNumber());
        $reader->openFile();
        $reader->readHeaders();
        $reader->setHeaderMap(['name' => 'Your_Name']);
        $this->assertEquals(1, $reader->getLastLineNumber());
        $map = $reader->getHeaderMap();
        $this->assertNotEmpty($reader->getHeaderMap());
        $this->assertArrayHasKey('name', $map);
        $this->assertEquals('Your_Name', $map['name']);

        $lineData = $reader->getNextLine();
        $this->assertArrayHasKey('Your_Name', $lineData);

        $reader->closeFile();
    }

    public function testValidFileReadLines()
    {
        $reader = new Reader(__DIR__ . '/../data/mock_data_valid.csv');
        $reader->openFile();
        $reader->readHeaders();
        while (null !== $data = $reader->getNextLine()) {
            $this->assertTrue(is_array($data));
        }
        $reader->closeFile();
    }

    public function testInvalidFileReadLines()
    {
        $reader = new Reader(__DIR__ . '/../data/mock_data_invalid.csv');
        $reader->openFile();
        $reader->readHeaders();
        $invalid = 0;
        while (null !== $data = @$reader->getNextLine()) {
            if ($data !== false) {
                $this->assertTrue(is_array($data));
            } else {
                $invalid++;
            }
        }
        $reader->closeFile();
        $this->assertGreaterThan(0, $invalid);
    }

    public function testReadAll()
    {
        $reader = new Reader(__DIR__ . '/../data/mock_data_valid.csv');
        $allData = $reader->getAllData();
        $this->assertNotEmpty($allData);
    }
}
