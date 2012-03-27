<?php
/**
 * (c) 2011-2012 Vespolina Project http://www.vespolina-project.org
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Vespolina\CartBundle\Model;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use Vespolina\CartBundle\Model\CartableItemInterface;
use Vespolina\CartBundle\Model\CartInterface;
use Vespolina\CartBundle\Model\CartItemInterface;
use Vespolina\CartBundle\Model\CartManagerInterface;
use Vespolina\CartBundle\Pricing\CartPricingProviderInterface;

/**
 * @author Daniel Kucharski <daniel@xerias.be>
 * @author Richard Shank <develop@zestic.com>
 */
abstract class CartManager implements CartManagerInterface
{
    protected $cartClass;
    protected $cartItemClass;
    protected $pricingProvider;
    protected $recurringInterface;

    // todo: $recurringInterface should be handled in a handler
    function __construct(CartPricingProviderInterface $pricingProvider, $cartClass, $cartItemClass, $recurringInterface = 'Vespolina\ProductSubscriptionBundle\Model\RecurringInterface')
    {
        $this->cartClass = $cartClass;
        $this->cartItemClass = $cartItemClass;
        $this->pricingProvider = $pricingProvider;
        $this->recurringInterface = $recurringInterface;
    }

    public function addItemToCart(CartInterface $cart, CartableItemInterface $cartableItem, $flush = true)
    {
        $item = $this->doAddItemToCart($cart, $cartableItem);
        $this->updateCart($cart, $flush);

        return $item;
    }

    public function removeItemFromCart(CartInterface $cart, CartableItemInterface $cartableItem, $flush = true)
    {
        $this->doRemoveItemFromCart($cart, $cartableItem);
        $this->updateCart($cart, $flush);
    }

    /**
     * @inheritdoc
     */
    public function createCart($cartType = 'default')
    {
        $cart = new $this->cartClass($cartType);
        $this->initCart($cart);

        return $cart;
    }

    /**
     * @inheritdoc
     */
    public function createItem(CartableItemInterface $cartableItem = null)
    {
        $cartItem = new $this->cartItemClass($cartableItem);
        $this->initCartItem($cartItem);

        return $cartItem;
    }

    public function getPricingProvider()
    {
        return $this->pricingProvider;
    }

    public function initCart(CartInterface $cart)
    {
        //Set default state (for now we set it to "open")
        $this->setCartState($cart, Cart::STATE_OPEN);

        //Create the pricing set to hold cart level pricing data
        $this->setCartPricingSet($cart, $this->pricingProvider->createPricingSet());
    }

    public function initCartItem(CartItemInterface $cartItem)
    {
        //Default cart item description to the product name
        if ($cartableItem = $cartItem->getCartableItem()) {
            $cartItem->setName($cartableItem->getCartableName());
            $cartItem->setDescription($cartItem->getName());
            $rpPricingSet = new \ReflectionProperty($cartItem, 'pricingSet');
            $rpPricingSet->setAccessible(true);
            $rpPricingSet->setValue($cartItem, $this->getPricingProvider()->createPricingSet());
            $rpPricingSet->setAccessible(false);

            if ($cartableItem instanceof $this->recurringInterface) {
                $rp = new \ReflectionProperty($cartItem, 'isRecurring');
                $rp->setAccessible(true);
                $rp->setValue($cartItem, true);
                $rp->setAccessible(false);
            }
        }
    }

    public function determinePrices(CartInterface $cart, $determineItemPrices = true)
    {
        $pricingProvider = $this->getPricingProvider();
        $pricingProvider->determineCartPrices($cart, null, $determineItemPrices);
    }

    public function setCartPricingSet(CartInterface $cart, $pricingSet)
    {
        $rp = new \ReflectionProperty($cart, 'pricingSet');
        $rp->setAccessible(true);
        $rp->setValue($cart, $pricingSet);
        $rp->setAccessible(false);
    }

    public function setCartState(CartInterface $cart, $state)
    {
        $rp = new \ReflectionProperty($cart, 'state');
        $rp->setAccessible(true);
        $rp->setValue($cart, $state);
        $rp->setAccessible(false);
    }

    public function findItemInCart(CartInterface $cart, CartableItemInterface $cartableItem)
    {
        foreach ($cart->getItems() as $item)
        {
            if ($item->getCartableItem() == $cartableItem) {
                return $item;
            };
        }

        return null;
    }

    protected function doAddItemToCart(CartInterface $cart, CartableItemInterface $cartableItem)
    {
        if ($item = $this->findItemInCart($cart, $cartableItem)) {
            // todo: if an item is already in the cart, increase the quantity
        } else {
            $item = $this->createItem($cartableItem);

            // add item to cart
            $rm = new \ReflectionMethod($cart, 'addItem');
            $rm->setAccessible(true);
            $rm->invokeArgs($cart, array($item));
            $rm->setAccessible(false);
        }
        $this->determinePrices($cart);

        return $item;
    }

    protected function doRemoveItemFromCart(CartInterface $cart, CartableItemInterface $cartableItem)
    {
        $item = $this->findItemInCart($cart, $cartableItem);

        // add item to cart
        $rm = new \ReflectionMethod($cart, 'removeItem');
        $rm->setAccessible(true);
        $rm->invokeArgs($cart, array($item));
        $rm->setAccessible(false);

        $this->determinePrices($cart);
    }
}
