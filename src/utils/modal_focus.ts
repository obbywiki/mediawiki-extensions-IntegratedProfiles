export function contain_modal_focus( dialog: HTMLElement, return_focus: HTMLElement | null = null ): () => void {
	const previous_focus = return_focus || ( document.activeElement instanceof HTMLElement ? document.activeElement : null );
	const background = new Map<HTMLElement, string | null>();

	function make_background_inert(): void {
		for ( const element of document.body.children ) {
			if ( !( element instanceof HTMLElement ) || element.contains( dialog ) || background.has( element ) ) { continue; }

			background.set( element, element.getAttribute( 'inert' ) );
			element.setAttribute( 'inert', '' );
		}
	}

	function on_keydown( event: KeyboardEvent ): void {
		if ( event.key !== 'Tab' ) { return; }
		const controls = Array.from( dialog.querySelectorAll<HTMLElement>(
			'button, input, select, textarea, a[href], [tabindex]',
		) ).filter( ( element ) => element.tabIndex >= 0 && !element.matches( ':disabled' ) &&
			!element.closest( '[inert], [hidden]' ) && element.getClientRects().length > 0 &&
			getComputedStyle( element ).visibility === 'visible' );

		event.preventDefault();
		event.stopPropagation();
		if ( !controls.length ) {
			dialog.focus();
			return;
		}

		const current = controls.indexOf( document.activeElement as HTMLElement );
		const next = current < 0 ? ( event.shiftKey ? controls.length - 1 : 0 ) : ( current + ( event.shiftKey ? -1 : 1 ) + controls.length ) % controls.length;
		controls[ next ].focus();
	}

	function on_focusin( event: FocusEvent ): void {
		if ( event.target instanceof Node && !dialog.contains( event.target ) ) {
			dialog.focus();
		}
	}

	make_background_inert();
	const observer = new MutationObserver( make_background_inert );
	observer.observe( document.body, { childList: true } );
	document.addEventListener( 'keydown', on_keydown, true );
	document.addEventListener( 'focusin', on_focusin );

	return () => {
		observer.disconnect();
		document.removeEventListener( 'keydown', on_keydown, true );
		document.removeEventListener( 'focusin', on_focusin );

		for ( const [ element, inert ] of background ) {
			if ( inert === null ) {
				element.removeAttribute( 'inert' );
			} else {
				element.setAttribute( 'inert', inert );
			}
		}

		if ( previous_focus?.isConnected ) { previous_focus.focus(); }
	};
}
