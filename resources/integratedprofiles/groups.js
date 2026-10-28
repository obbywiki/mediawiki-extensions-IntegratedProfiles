'use strict';

function init_groups() {
	document.querySelectorAll( '.ip-identity__groups' ).forEach( ( groups ) => {
		if ( groups.dataset.ipGroupsBound === '1' ) { return; }

		const roles = Array.from( groups.querySelectorAll( '.ip-identity__group' ) );
		if ( !roles.length ) { return; }

		groups.dataset.ipGroupsBound = '1';

		const item = document.createElement( 'li' );
		item.className = 'ip-identity__groups-control';

		const toggle = document.createElement( 'button' );
		toggle.type = 'button';
		toggle.className = 'ip-identity__groups-toggle';

		let expanded = false;
		function update() {
			let visible = Math.min( 3, roles.length );
			groups.classList.toggle( 'ip-identity__groups--expanded', expanded );
			function render() {
				roles.forEach( ( role, index ) => { role.hidden = !expanded && index >= visible; } );

				item.hidden = !expanded && visible === roles.length;
				toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );

				const label = expanded ? mw.msg( 'integratedprofiles-groups-less' ) : mw.msg( 'integratedprofiles-groups-more', roles.length - visible );
				toggle.setAttribute( 'aria-label', label );
				toggle.title = label;
				toggle.textContent = expanded ? '-' : mw.msg( 'integratedprofiles-groups-count', roles.length - visible );
			}

			render();

			if ( !expanded && groups.clientWidth > 0 ) {
				while ( visible > 0 && groups.scrollWidth > groups.clientWidth ) {
					visible--;
					render();
				}
			}
		}

		toggle.addEventListener( 'click', () => {
			expanded = !expanded;
			update();
		} );

		item.appendChild( toggle );
		groups.appendChild( item );
		update();

		if ( typeof ResizeObserver === 'function' ) {
			const observer = new ResizeObserver( update );
			observer.observe( groups );
		} else {
			window.addEventListener( 'resize', update );
		}

		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( update );
		}
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init_groups );
} else {
	init_groups();
}
