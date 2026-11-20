import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

const root_dir = dirname( fileURLToPath( import.meta.url ) );

export default defineConfig( ( { mode } ) => {
	const build_upload_modals = mode === 'upload-modals';

	return {
		plugins: [ vue() ],
		build: {
			outDir: 'resources/dist',
			emptyOutDir: !build_upload_modals,
			lib: {
				entry: resolve( root_dir, build_upload_modals ? 'src/app/upload_modals.ts' : 'src/app/main.ts' ),
				formats: [ 'cjs' ],
				fileName: () => build_upload_modals ? 'integratedprofiles-upload-modals.js' : 'integratedprofiles-app.js'
			},
			rollupOptions: {
				external: [ 'vue' ],
				output: {
					exports: 'none'
				}
			}
		}
	};
} );
