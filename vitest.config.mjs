import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig( {
	plugins: [
		vue()
	],
	test: {
		include: [ 'tests/vitest/**/*.test.ts', 'tests/vitest/**/*.test.js' ],
		globals: true,
		environment: 'jsdom',
		coverage: {
			provider: 'v8',
			reporter: [ 'text', 'lcov' ],
			reportsDirectory: 'coverage/js',
		},
	}
} );
