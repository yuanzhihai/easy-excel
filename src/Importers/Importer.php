<?php

namespace Dcat\EasyExcel\Importers;

use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Dcat\EasyExcel\Contracts;
use Dcat\EasyExcel\Contracts\Sheet as SheetInterface;
use Dcat\EasyExcel\Excel as ExcelConstants;
use Dcat\EasyExcel\Support\SheetCollection;
use Dcat\EasyExcel\Support\Traits\Macroable;
use Dcat\EasyExcel\Traits\Excel;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class Importer.
 *
 * @author jqh <841324345@qq.com>
 */
class Importer implements Contracts\Importer
{
    use Macroable, Excel, TempFile;

    /**
     * @var ReaderInterface
     */
    protected $reader;

    /**
     * @var string|UploadedFile
     */
    protected $filePath;

    /**
     * @var int|\Closure
     */
    public $headingRow = 1;

    public function __construct($filePath)
    {
        $this->file($filePath);
    }

    /**
     * @param string|UploadedFile $filePath
     * @return $this
     */
    public function file($filePath)
    {
        $this->filePath = $filePath;

        return $this;
    }

    /**
     * @param int|\Closure $lineNumberOrCallback
     * @return mixed
     */
    public function headingRow($lineNumberOrCallback)
    {
        $this->headingRow = $lineNumberOrCallback;

        return $this;
    }

    /**
     * @return Contracts\Sheets
     *
     * @throws FileNotFoundException|FilesystemException
     * @throws IOException
     * @throws UnsupportedTypeException
     */
    public function sheets()
    {
        try {
            $filePath = $this->prepareFileName($this->filePath);

            if (is_string($filePath) && ($filesystem = $this->filesystem())) {
                $filePath = $this->moveFileToTemp($filesystem, $filePath);
            }

            $reader = $this->makeReader($filePath);

            return new LazySheets($this->readSheets($reader));
        } catch (\Throwable $e) {
            $this->releaseResources();

            throw $e;
        }
    }

    /**
     * 根据名称或序号获取sheet.
     *
     * @param int|string $indexOrName
     * @return Contracts\Sheet
     *
     * @throws FileNotFoundException
     * @throws IOException
     * @throws UnsupportedTypeException
     */
    public function sheet($indexOrName): Contracts\Sheet
    {
        return $this->sheets()->index($indexOrName) ?: $this->makeNullSheet();
    }

    /**
     * @return array
     *
     * @throws FileNotFoundException
     * @throws IOException
     * @throws UnsupportedTypeException
     */
    public function toArray(): array
    {
        return $this->sheets()->toArray();
    }

    /**
     * @return SheetCollection
     *
     * @throws FileNotFoundException
     * @throws IOException
     * @throws UnsupportedTypeException
     */
    public function collect(): SheetCollection
    {
        return $this->sheets()->collect();
    }

    /**
     * @param callable $callback
     * @return $this
     *
     * @throws FileNotFoundException
     * @throws IOException
     * @throws UnsupportedTypeException
     */
    public function each(callable $callback)
    {
        $this->sheets()->each($callback);

        return $this;
    }

    /**
     * 获取第一个sheet.
     *
     * @return Contracts\Sheet
     *
     * @throws FileNotFoundException
     * @throws IOException
     * @throws UnsupportedTypeException
     */
    public function first(): Contracts\Sheet
    {
        $sheet = null;

        $this->sheets()->each(function (SheetInterface $value) use (&$sheet) {
            $sheet = $value;

            return false;
        });

        return $sheet ?: $this->makeNullSheet();
    }

    /**
     * 获取当前打开的sheet.
     *
     * @return Contracts\Sheet
     *
     * @throws FileNotFoundException
     * @throws IOException
     * @throws UnsupportedTypeException
     * @throws UnsupportedTypeException
     */
    public function active(): Contracts\Sheet
    {
        $sheet = null;

        $this->sheets()->each(function (SheetInterface $value) use (&$sheet) {
            if ($value->isActive()) {
                $sheet = $value;

                return false;
            }
        });

        return $sheet ?: $this->makeNullSheet();
    }

    /**
     * @param \OpenSpout\Reader\ReaderInterface $reader
     * @return \Generator
     *
     * @throws \OpenSpout\Reader\Exception\ReaderNotOpenedException
     */
    protected function readSheets(ReaderInterface $reader)
    {
        foreach ($reader->getSheetIterator() as $key => $sheet) {
            yield new Sheet($this, $sheet);
        }

        $this->releaseResources();
    }

    /**
     * @param string|UploadedFile $path
     * @return \OpenSpout\Reader\ReaderInterface
     *
     * @throws \OpenSpout\Common\Exception\UnsupportedTypeException
     * @throws \OpenSpout\Common\Exception\IOException
     */
    protected function makeReader($path)
    {
        $extension = null;
        if ($path instanceof UploadedFile) {
            $extension = $path->guessClientExtension();
            $path      = $path->getRealPath();
        }

        $type = $this->type ?: $extension ?: strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $reader = $this->createReaderByType($type);

        $reader->open($path);

        $this->configure($reader);

        return $this->reader = $reader;
    }

    /**
     * @throws UnsupportedTypeException
     */
    protected function createReaderByType(string $type): ReaderInterface
    {
        $csvConfig = $this->getCsvConfiguration();

        switch ($type) {
            case ExcelConstants::CSV:
                $options                  = new CsvOptions;
                $options->FIELD_DELIMITER = $csvConfig['delimiter'];
                $options->FIELD_ENCLOSURE = $csvConfig['enclosure'];
                $options->ENCODING        = $csvConfig['encoding'];

                return new CsvReader($options);

            case ExcelConstants::XLSX:
                return new XlsxReader;

            case ExcelConstants::ODS:
                return new OdsReader;

            default:
                throw new UnsupportedTypeException('No readers supporting the given type: ' . $type);
        }
    }

    /**
     * @return NullSheet
     */
    protected function makeNullSheet()
    {
        return new NullSheet();
    }

    /**
     * @return void
     */
    protected function releaseResources()
    {
        if ($this->reader) {
            $this->reader->close();
        }

        $this->removeTempFile();
    }
}
