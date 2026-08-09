<?php
use CrescoCanvas\Forms\FormAdministration;
use PHPUnit\Framework\TestCase;
final class CsvSecurityTest extends TestCase {
	public function test_formula_prefixes_are_neutralized_even_after_whitespace(): void {
		foreach(array('=1+1','+cmd','-2+3','@SUM(A1:A2)'," \t=HYPERLINK(\"x\")") as $cell) self::assertStringStartsWith("'",FormAdministration::safe_csv_cell($cell),$cell);
	}
	public function test_normal_cells_are_not_prefixed(): void { self::assertSame('customer@example.test',FormAdministration::safe_csv_cell('customer@example.test')); }
	public function test_cells_are_bounded(): void { self::assertLessThanOrEqual(FormAdministration::MAX_CELL_BYTES,strlen(FormAdministration::safe_csv_cell(str_repeat('a',FormAdministration::MAX_CELL_BYTES+100)))); }
}
