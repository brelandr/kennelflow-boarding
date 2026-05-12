import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(
	{
		plugins: [ react() ],
		define: {
			'process.env.NODE_ENV': JSON.stringify( 'production' ),
		},
		build: {
			outDir: 'assets/dist',
			emptyOutDir: false,
			lib: {
				entry: 'assets/src/facility-settings.jsx',
				name: 'KennelFlowBoardingFacilitySettings',
				formats: [ 'iife' ],
				fileName: () => 'facility-settings.js',
			},
			rollupOptions: {
				output: {
					inlineDynamicImports: true,
					assetFileNames: 'facility-settings.[ext]',
				},
			},
		},
	}
);
