@props(['order'])
@if(auth()->user()->role?->name === 'ADMIN' && !$order->admin_deleted_at)
    @php($canDelete = in_array($order->status, ['CANCELLED', 'REFUSED', 'COMPLETED'], true))
    <div style="margin:16px 0">
        <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" onsubmit="return confirm('Supprimer cette commande de la liste ? Son historique et ses documents seront conservés.');">
            @csrf @method('DELETE')
            <button class="button" type="submit" @disabled(!$canDelete) style="background:{{ $canDelete ? '#b42318' : '#e5e7eb' }};color:{{ $canDelete ? '#fff' : '#59616c' }}" aria-describedby="delete-help-{{ $order->id }}">Supprimer la commande</button>
        </form>
        <p id="delete-help-{{ $order->id }}"><small>
            @if($canDelete)
                La commande sera retirée de la liste. Son historique sera conservé.
            @else
                Cette commande est encore en cours. <a href="{{ route('admin.orders.show', $order) }}#traiter-commande" style="text-decoration:underline">Annulez-la ou terminez-la dans « Traiter la commande »</a> pour activer la suppression.
            @endif
        </small></p>
    </div>
@endif
