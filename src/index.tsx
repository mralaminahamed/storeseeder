import React from 'react';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import App from '@/components/App';
import './styles.css';
import '@/components.css';

domReady( () => {
	const container = document.getElementById( 'storeseeder-root' )!;
	if ( container ) {
		const root = createRoot( container );
		root.render( <App /> );
	}
} );
