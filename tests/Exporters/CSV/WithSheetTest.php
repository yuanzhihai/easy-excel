<?php

namespace Tests\Exporters\CSV;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use Dcat\EasyExcel\Excel;
use Dcat\EasyExcel\Support\SheetCollection;
use Tests\Exporters\Exporter;
use Tests\TestCase;

class WithSheetTest extends TestCase
{
    use Exporter;

    /**
     * @group exporter
     */
    public function test()
    {
        $users = include __DIR__.'/../../resources/users.php';

        $storePath = $this->generateTempFilePath('csv');

        $headingStyle = new Style;
        $headingStyle->setFontColor(Color::BLUE);
        $headingStyle->setFontSize(14);

        $sheet = Excel::createSheet($users)
            ->headingStyle($headingStyle)
            ->row(function (array $row) {
                $style = new Style;
                $style->setFontColor(Color::rgb(128, 0, 128)); // Purple
                $style->setFontSize(14);

                return Row::fromValues($row, $style);
            });

        // 保存
        Excel::export($sheet)->store($storePath);

        // 读取
        $this->assertSingleSheet($storePath, 0, $users);

        /*
        |---------------------------------------------------------------
        | 测试多个sheet
        |---------------------------------------------------------------
       */
        $users1 = new SheetCollection(array_slice($users, 0, 30));
        $users2 = new SheetCollection(array_values(array_slice($users, 30, 30)));

        $storePath = $this->generateTempFilePath('csv');

        $sheet1 = Excel::createSheet($users1, 'sheet1');
        $sheet2 = Excel::createSheet($users2, 'sheet2');

        // 保存
        Excel::export([$sheet1, $sheet2])->store($storePath);

        // 读取
        $this->assertSingleSheet($storePath, 0, $users);
    }

    public function testWithChunkQuery()
    {
        $users = include __DIR__.'/../../resources/users.php';

        $users1 = new SheetCollection(array_slice($users, 0, 30));
        $users2 = new SheetCollection(array_values(array_slice($users, 30, 30)));

        $storePath = $this->generateTempFilePath('csv');

        $sheet1 = Excel::createSheet()->name('sheet1')->chunk(function (int $times) use ($users1) {
            return $users1->forPage($times, 10);
        });

        $sheet2 = Excel::createSheet()->name('sheet2')->chunk(function (int $times) use ($users2) {
            return $users2->forPage($times, 10);
        });

        // 保存
        Excel::export([$sheet1, $sheet2])->store($storePath);

        // 读取
        $this->assertSingleSheet($storePath, 0, $users);
    }
}
