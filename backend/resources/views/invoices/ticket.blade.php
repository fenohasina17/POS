<!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><title>Facture</title></head>
            <body>
                <h1>Facture #{{ $ticket_number }}</h1>
                <p>Date: {{ $date }}</p>
                <p>Table: {{ $table }}</p>
                <p>Caissier: {{ $cashier }}</p>
                <h2>Articles</h2>
                @foreach($items_by_category as $category => $items)
                    <h3>{{ $category }}</h3>
                    @foreach($items as $item)
                        <p>{{ $item->product->name }} x{{ $item->quantity }} = {{ number_format($item->total, 0, ",", " ") }} Ar</p>
                    @endforeach
                @endforeach
                <p>Total: {{ number_format($final_amount, 0, ",", " ") }} Ar</p>
            </body>
            </html>