/**
 * Block editor counterpart of the "Pushbericht" toggle in the classic submit box
 * (lib/push_adapter.php). Shown as a row in the Summary panel. No build step.
 */
( function ( wp ) {
	const { createElement: h } = wp.element;
	const { Button } = wp.components;
	const { PluginPostStatusInfo } = wp.editor;
	const { useEntityProp } = wp.coreData;

	function PushToggle() {
		const [ meta, setMeta ] = useEntityProp( 'postType', 'post', 'meta' );
		const enabled = !! meta?.push_post;

		return h(
			PluginPostStatusInfo,
			{ className: 'zw-push-toggle' },
			h(
				'div',
				{ className: 'editor-post-panel__row' },
				h(
					'div',
					{ className: 'editor-post-panel__row-label' },
					'Pushbericht'
				),
				h(
					'div',
					{ className: 'editor-post-panel__row-control' },
					h(
						Button,
						{
							size: 'compact',
							variant: 'tertiary',
							'aria-label':
								'Pushbericht ' +
								( enabled ? 'uitzetten' : 'aanzetten' ),
							// Send only this key, not the whole meta object.
							onClick: () => setMeta( { push_post: ! enabled } ),
						},
						enabled ? 'Ja' : 'Nee'
					)
				)
			)
		);
	}

	wp.plugins.registerPlugin( 'streekomroep-push-toggle', {
		render: PushToggle,
	} );
} )( window.wp );
