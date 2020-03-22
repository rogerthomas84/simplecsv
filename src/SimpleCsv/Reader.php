<?php
namespace SimpleCsv;

use SimpleCsv\Exception\FileDoesNotExistException;
use SimpleCsv\Exception\FileNotReadableException;
use SimpleCsv\Exception\FileOpenFailedException;
use SimpleCsv\Exception\HeadersAlreadySetupException;
use SimpleCsv\Exception\HeadersNotSetupException;
use SimpleCsv\Exception\InvalidCsvLineDetectedException;

/**
 * Class Reader
 * @package SimpleCsv
 */
class Reader
{
    /**
     * @var string
     */
    protected $filePath = null;

    /**
     * @var string
     */
    protected $delimiter;

    /**
     * @var string
     */
    protected $enclosure;

    /**
     * @var string
     */
    protected $escape;

    /**
     * @var resource|false
     */
    protected $fp = false;

    /**
     * @var bool
     */
    protected $opened = false;

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var array
     */
    protected $headerMap = [];

    /**
     * @var int
     */
    protected $lineNum = 0;

    /**
     * Reader constructor.
     *
     * @param string $filePath - The path to the file you're working with.
     * @param string $delimiter [optional] - Set the field delimiter (one character only).
     * @param string $enclosure [optional] - Set the field enclosure character (one character only).
     * @param string $escape [optional] - Set the escape character (one character only). Defaults as a backslash (\)
     */
    public function __construct($filePath, $delimiter = ',', $enclosure = '"', $escape = '\\')
    {
        $this->filePath = $filePath;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape = $escape;
    }

    /**
     * @throws FileDoesNotExistException
     * @throws FileNotReadableException
     * @throws FileOpenFailedException
     */
    public function openFile()
    {
        $this->setupFile();
        $this->opened = true;
    }

    /**
     * Get the next line. Returns :
     *  array (either numeric or associative) on successful read.
     *  boolean false for a field count mismatch
     *  null for other read failure. Which could indicate the end of the file or at least a blank line.
     *
     * @param bool $asAssociativeArray (optional) default true - whether to use the headers to map the array.
     * @return array|null|false
     * @throws FileDoesNotExistException
     * @throws FileNotReadableException
     * @throws FileOpenFailedException
     * @throws HeadersNotSetupException
     */
    public function getNextLine($asAssociativeArray = true)
    {
        if (!$this->opened) {
            $this->openFile();
        }
        $this->checkHeaders();
        try {
            $data = $this->fetchParsedLine();
            if (count($data) !== count($this->getHeaders())) {
                trigger_error(
                    sprintf(
                        'Field mismatch. Line %s contained %s fields, the headers contain %s fields.',
                        $this->getLastLineNumber(),
                        count($data),
                        count($this->getHeaders())
                    )
                );
                return false;
            }
            if ($asAssociativeArray === false) {
                return $data;
            }
            return $this->convertToMapped(
                $data
            );

        } catch (InvalidCsvLineDetectedException $e) {
        }
        return null;
    }

    /**
     * @param array $headers
     * @return $this
     * @throws HeadersAlreadySetupException
     */
    public function setHeaders(array $headers)
    {
        if ($this->hasHeaders() === true) {
            throw new HeadersAlreadySetupException(
                'You have already either read or set headers.'
            );
        }
        $this->headers = $headers;
        foreach ($this->headers as $_ => $header) {
            $this->headerMap[$header] = $header;
        }
        return $this;
    }

    /**
     * Map header keys to new keys.
     *
     * @param array $headerMap
     * @return $this
     * @example ->setHeaderMap(
     *     [
     *     //  'old name'   => 'new name'
     *         'first_name' => 'firstName'
     *     ]
     * )
     */
    public function setHeaderMap(array $headerMap)
    {
        foreach ($headerMap as $k => $v) {
            $this->headerMap[$k] = $v;
        }
        return $this;
    }

    /**
     * Get the header map.
     *
     * @return array
     * @example [
     *      'first_name' => 'firstName'
     * ]
     */
    public function getHeaderMap()
    {
        return $this->headerMap;
    }

    /**
     * Get the headers.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get the last line number read.
     *
     * @return int
     */
    public function getLastLineNumber()
    {
        return $this->lineNum;
    }

    /**
     * @return array
     * @throws FileDoesNotExistException
     * @throws FileNotReadableException
     * @throws FileOpenFailedException
     * @throws HeadersAlreadySetupException
     * @throws InvalidCsvLineDetectedException
     */
    public function readHeaders()
    {
        if (!$this->opened) {
            $this->openFile();
        }
        if ($this->hasHeaders() === true) {
            throw new HeadersAlreadySetupException(
                'You have already either read or set headers.'
            );
        }
        $headers = $this->fetchParsedLine();
        $this->setHeaders($headers);

        return $this->headers;
    }

    /**
     * @return array
     * @throws FileDoesNotExistException
     * @throws FileNotReadableException
     * @throws FileOpenFailedException
     * @throws HeadersAlreadySetupException
     * @throws HeadersNotSetupException
     * @throws InvalidCsvLineDetectedException
     */
    public function getAllData()
    {
        if (!$this->opened) {
            $this->openFile();
        }
        if ($this->hasHeaders() === false) {
            $this->readHeaders();
        }
        $allData = [];
        while (null !== $data = @$this->getNextLine()) {
            if ($data !== false) {
                $allData[] = $data;
            }
        }
        return $allData;
    }

    /**
     * Close the file pointer and reset all values. This
     * leaves the file in a new readable state.
     *
     * @return bool
     */
    public function closeFile()
    {
        $result = @fclose($this->fp);
        $this->fp = false;
        $this->lineNum = 0;
        $this->headerMap = [];
        $this->headers = [];
        $this->opened = false;

        return $result;
    }

    /**
     * @return bool
     */
    protected function hasHeaders()
    {
        return count($this->getHeaders()) > 0;
    }

    /**
     * @return array
     * @throws InvalidCsvLineDetectedException
     */
    protected function fetchParsedLine()
    {
        $this->lineNum++;
        $line = stream_get_line($this->fp, 1024 * 1024, PHP_EOL);
        if (false === $line) {
            throw new InvalidCsvLineDetectedException(
                sprintf('Line %s was invalid and contained no data.', $this->lineNum)
            );
        }
        $data = str_getcsv($line, $this->delimiter, $this->enclosure, $this->escape);
        if (empty($data)) {
            throw new InvalidCsvLineDetectedException(
                sprintf('Line %s was invalid and contained no data.', $this->lineNum)
            );
        }
        return $data;
    }

    /**
     * @throws HeadersNotSetupException
     */
    protected function checkHeaders()
    {
        if ($this->hasHeaders() === false) {
            throw new HeadersNotSetupException(
                'Call readHeaders() or setHeaders([]) before reading file.'
            );
        }
    }

    /**
     * @throws FileDoesNotExistException
     * @throws FileNotReadableException
     * @throws FileOpenFailedException
     */
    protected function setupFile()
    {
        if (!file_exists($this->filePath) || !is_file($this->filePath)) {
            throw new FileDoesNotExistException(
                sprintf(
                    'Non existent file: %s',
                    $this->filePath
                )
            );
        }
        if (!is_readable($this->filePath)) {
            throw new FileNotReadableException(
                sprintf(
                    'Not readable: %s',
                    $this->filePath
                )
            );
        }
        $this->fp = fopen($this->filePath, 'r+');
        if (false === $this->fp) {
            throw new FileOpenFailedException(
                sprintf(
                    'Not readable: %s',
                    $this->filePath
                )
            );
        }
    }

    /**
     * Convert an array from a read line of data into a keyed value.
     *
     * @param array $data
     * @return array
     */
    protected function convertToMapped(array $data)
    {
        $final = [];
        foreach ($data as $n => $value) {
            $originalKey = $this->headers[$n];
            $mappedKey = $this->headerMap[$originalKey];
            $final[$mappedKey] = $value;
        }
        return $final;
    }
}
