// Get block functions
const { registerBlockType } = wp.blocks;

// Import our blocks
import * as exampleBlock from './example-block';

// Create a list of blocks to loop through
const blocks = [exampleBlock];

// Register the blocks
blocks.forEach(({ name, settings }) => {
	registerBlockType(name, settings);
});
