<?php // /app/views/ventes/pos.php ?>

<style>
    /* Styles pour l'interface POS */
    body {
        /* Empêche le défilement vertical de la page entière */
        overflow: hidden; 
    }
    .pos-container { 
        display: flex; 
        gap: 1.5rem; 
        /* Hauteur maximale pour remplir l'écran sans déborder */
        height: calc(100vh - 150px); 
    }
    .product-list { 
        flex: 3; 
        display: flex;
        flex-direction: column;
    }
    .product-grid-container {
        flex-grow: 1;
        overflow-y: auto; /* Ajoute une barre de défilement uniquement pour la liste des produits */
        padding-right: 10px;
    }
    .product-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); 
        gap: 1rem; 
    }
    .product-card { 
        border: 1px solid #ddd; 
        padding: 1rem; 
        text-align: center; 
        border-radius: 5px; 
        cursor: pointer; 
        background: #fff;
        height: 100px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .product-card:hover { 
        background: #e9f5ff;
        border-color: #007bff;
    }
    .product-card strong {
        font-size: 0.9em;
    }
    .cart { 
        flex: 2; 
        background: #fff; 
        padding: 1rem; 
        border-radius: 5px; 
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
    }
    #search-product {
        width: 100%;
        padding: 10px;
        margin-bottom: 1rem;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
</style>

<div class="pos-container">
    <div class="product-list">
        <input type="text" id="search-product" placeholder="Chercher un produit par nom ou référence...">
        <div class="product-grid-container">
            <div class="product-grid">
                <?php foreach($data['articles'] as $article): ?>
                    <a href="/ventes/api_ajouterAuPanier/<?php echo $article['id']; ?>" class="product-link" style="text-decoration:none; color:inherit;">
                        <div class="product-card" data-designation="<?php echo htmlspecialchars(strtolower($article['designation'])); ?>" data-reference="<?php echo htmlspecialchars(strtolower($article['reference'])); ?>">
                            <strong><?php echo htmlspecialchars($article['designation']); ?></strong>
                            <p><?php echo number_format($article['prix_vente_ht'] * (1 + $article['tva_taux']), 2); ?> DA</p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="cart" id="panier-container">
        <p style="text-align:center; margin-top: 50px;">Chargement du panier...</p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    function updateCart(url) {
        $('#panier-container').load(url);
    }
    updateCart('/ventes/api_afficherPanier');

    $('.product-grid').on('click', '.product-link', function(e) {
        e.preventDefault();
        updateCart($(this).attr('href'));
    });

    $('#panier-container').on('click', '#btn-vider-panier', function(e) {
        e.preventDefault();
        if(confirm('Voulez-vous vraiment vider le panier ?')) {
            updateCart('/ventes/api_viderPanier');
        }
    });

    $('#search-product').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.product-card').each(function() {
            const designation = $(this).data('designation');
            const reference = $(this).data('reference');
            if (designation.includes(searchTerm) || reference.includes(searchTerm)) {
                $(this).parent().show();
            } else {
                $(this).parent().hide();
            }
        });
    });

    // On utilise la délégation car le panier est rechargé par AJAX
    $(document).on('change', '#methode_paiement', function() {
        var selectedMethod = $(this).val();
        if (selectedMethod === 'TPE' || selectedMethod === 'Virement') {
            $('#champ_reference_paiement').slideDown();
        } else {
            $('#champ_reference_paiement').slideUp();
        }
    });

    // Logique pour les boutons de vente à crédit
    $(document).on('change', '#client_id_selector', function() {
        var selectedClient = $(this).val();
        if (selectedClient !== '0') {
            $('#btn-credit-sale').prop('disabled', false).removeClass('btn-secondary').addClass('btn-warning');
        } else {
            $('#btn-credit-sale').prop('disabled', true).removeClass('btn-warning').addClass('btn-secondary');
        }
    });
});
</script>