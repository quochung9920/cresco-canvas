<?php
/**
 * Advanced Dynamic Data regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Dynamic\AdvancedDynamicData;
use PHPUnit\Framework\TestCase;

final class AdvancedDynamicDataTest extends TestCase {
	public function test_describe_value_does_not_expose_raw_content(): void {
		self::assertSame( array( 'type' => 'empty', 'count' => 0 ), AdvancedDynamicData::describe_value( array() ) );
		self::assertSame( array( 'type' => 'array', 'count' => 3 ), AdvancedDynamicData::describe_value( array( 'secret', 'values', 'hidden' ) ) );
		self::assertSame( array( 'type' => 'string', 'count' => 1 ), AdvancedDynamicData::describe_value( 'private value' ) );
	}

	public function test_gallery_normalization_rejects_non_image_values(): void {
		$images = AdvancedDynamicData::normalize_gallery(
			array(
				array( 'url' => 'https://example.com/image.jpg', 'alt' => 'Example' ),
				array( 'url' => 'javascript:alert(1)' ),
				array( 'foo' => 'bar' ),
			)
		);

		self::assertCount( 1, $images );
		self::assertSame( 'https://example.com/image.jpg', $images[0]['url'] );
		self::assertSame( 'Example', $images[0]['alt'] );
	}

	public function test_gallery_is_bounded_to_twenty_four_items(): void {
		$items = array_fill( 0, 40, 'https://example.com/image.jpg' );
		self::assertCount( 24, AdvancedDynamicData::normalize_gallery( $items ) );
	}
}
