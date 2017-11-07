<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Group;
use App\Product;
use App\ProductPrice;
use App\ProductCombination;
use App\User;
use App\Address;
use App\Order;
use App\OrderProduct;
use App\OrderProductDetail;
use App\OrderTotal;
use App\OrderStatus;
use App\OrderStatusHistory;
use Collective\Html\Eloquent\FormAccessible;
use Darryldecode\Cart\Facades\CartFacade as Cart;
class AjaxController extends Controller
{
    public function  __construct()
    {
        parent::__construct();
    }
    /**
     * User Addresses.
     *
     * @param  Request  $request
     * @param  User  $user
     * @return \Illuminate\Http\Response
     */
    public function citylist($state)
    {
        $citylist = DB::table('geo_locations')
            ->where('geo_locations.state','=',$state)->orderBy('name', 'asc')->pluck("name","id");

        return view("ajax.citylist",compact('citylist'));
    }

 /**
     * User Addresses.
     *
     * @param  Request  $request
     * @param  User  $user
     * @return \Illuminate\Http\Response
     */
    public function set_default_address(Address $address)
    {
			DB::table('addresses')
            ->where('user_id', $address->user_id)
            ->update(['default_address' => 0]);
        $address->default_address=1;
        $address->save();
        return $address;
    }
 /**
     * Thread id
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function stared($id)
    {
            DB::table('messenger_participants')
            ->where('user_id', Auth::id())
            ->where('thread_id', $id)
            ->update(['stared' => 1]);
        return back();
    }
 /**
     * Thread id
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function unstared($id)
    {
            DB::table('messenger_participants')
            ->where('user_id', Auth::id())
            ->where('thread_id', $id)
            ->update(['stared' => 0]);
        return back();
    }
 /**
     * User Addresses.
     *
     * @param  Request  $request
     * @param  User  $user
     * @return \Illuminate\Http\Response
     */
    public function order_userchange()
    {
        $uid=request('value');
        $user=User::find($uid);
        $addresses=Address::where("user_id","=",$uid)->orderBy('default_address', 'desc')->get();
        $default_address=Address::where("user_id","=",$uid)->where('default_address', '=','1')->get()->first();
        $addressArray=array();
        foreach ($addresses as $key => $value) {
            $addressArray[]=array('id'=>$value->id,"value"=>htmlentities($value->printFullAddressWithPhoneEmail()));
        }
        return array('addresses'=>$addressArray,'default_address'=>$default_address->id,'min_amount'=>$user->minimum_amount());
    }

    public function delete_from_cart($id)
    {
        Cart::remove($id);

        $data=array();
        $data['cart_menu']=view("adminlte::layouts.partials.cart_menu")->render();
        $data['toTop']=view("adminlte::layouts.partials.toTop")->render();
        $data['controlsidebar']=view("adminlte::layouts.partials.controlsidebarcart")->render();

        return $data;
    }
    public function Add_To_Cart_Checkout()
    {
        $this->Add_To_Cart();
        return redirect("/checkout/");
    }
    public function Add_To_Cart()
    {

        $input=request()->all();
        //Cart::clear();
        $itemId=$input['variation_id'];
        $item=Cart::get($itemId);
        if($item)
        {
            //Update
            //printe($item->quantity,$input['qty'],$item->quantity+$input['qty']);
            Cart::update($itemId, array(
              'quantity' => $input['qty'],
            ));
            $item=Cart::get($itemId);
            //printe($item);
        }
        else
        {
            //Add
            $pp=ProductPrice::find($itemId);
            //dd($itemId,$input,$pp->product->product_type);
            if($pp->product_id==$input["product_id"] && $pp->group_id==AUth::user()->getgroups())
            {
                $saleCondition = NULL;
                if($pp->vat)
                {
                    // lets create first our condition instance
                    $saleCondition = new \Darryldecode\Cart\CartCondition(array(
                            'name' => 'VAT '.$pp->vat.'%',
                            'type' => 'tax',
                            'target' => 'item',
                            'value' => '-'.$pp->vat.'%',
                        ));
                }
                $attributes=array();
                $attributes=$this->ProductAttributes($pp,$input);

                // now the product to be added on cart
                //dd($pp->product()->first(),$pp->variation_id);
                $cartItemId=getItemIdForCart($itemId,$input);
                $product = array(
                            'id' => $cartItemId,
                            'productid' => $itemId,
                            'name' => $pp->productVariationName(),
                            'price' => $pp->price,
                            'quantity' => $input['qty'],
                            'attributes' => $attributes,
                            'conditions' => $saleCondition
                        );
                // finally add the product on the cart
                Cart::add($product);
                //dd(Cart::getContent());
            }
            else
            {
                //return false;
            }
            //dd($pp);
        }
        $data=array();
        $data['cart_menu']=view("adminlte::layouts.partials.cart_menu")->render();;
        $data['toTop']=view("adminlte::layouts.partials.toTop")->render();
        $data['controlsidebar']=view("adminlte::layouts.partials.controlsidebarcart")->render();
        return $data;
    }

    public function Refresh_Cart()
    {
        $data=array();
        $data['cart_menu']=view("adminlte::layouts.partials.cart_menu")->render();
        $data['toTop']=view("adminlte::layouts.partials.toTop")->render();
        $data['controlsidebar']=view("adminlte::layouts.partials.controlsidebarcart")->render();
        return $data;
    }

    public function pincode_validate()
    {
        $code=request("pincode");
        $pincode = DB::table('pincodes')
            ->where('pincodes.pincode','=',$code)->first();

        $result=array();
        $result['message']='';
        $result['payment']='Not In Service';
        $result['message_email']='Not In Service';
        if(isset($pincode->pincode))
        {
            if($pincode->cod=='N' && $pincode->prepaid=='Y' )
            {
                $result['message']='Only Prepaid Order';
            }
            if($pincode->cod=='Y')
            {
                $result['message']='COD Available';
            }
            if($pincode->cod=='Y')
            {
                $payment[]='COD';
            }
            if($pincode->prepaid=='Y')
            {
                $payment[]='PREPAID';
            }
            $result['payment']=implode(",",$payment);
            $result['message_email']=$result['message'];
        }

        return $result;
    }


    private function ProductAttributes($pp,$input)
    {
        $attributes=array();
        if($pp->product->product_type==COMBINED_PRODUCT)
        {
            $attr=$input["attribute"][$input["product_id"]];
            $product=$pp->product;
            foreach($product->ProductCombination() as $combination)
            {
                $cpid=$combination->combination_product_id;
                $cpvid=$combination->combination_variation_id;
                if(isset($attr[$cpid][0]))
                {
                    $arr=array($cpid=>array($cpvid=>
                        array('qty' => $attr[$cpid][0])
                    ));
                }
                else
                {
                    $arr=array($cpid=>array($cpvid=>
                        array('qty' => 1)
                    ));
                }
                $cbattributes=array();
                if($combination->combine_product->product_type==SIMPLE_PRODUCT)
                {
                    $cbattributes=$this->GetSimpleProductAttributes($pp,$combination,$cpid,$cpvid,$attr);                }
                if($combination->combine_product->product_type==CUSTOM_PRODUCT)
                {
                    $cbattributes=$this->GetCustomProductAttributes($pp,$combination,$cpid,$cpvid,$attr);
                }


                $arr[$cpid][$cpvid]['attributes']=$cbattributes;
                $attributes[]=$arr;
            }
            //dd($attributes,$input);
            //dd($itemId,$input,$pp->product->product_type);
        }
        else
        {
            $variations=$pp->productVariations();
            if($variations)
            {
                foreach ($variations as $key => $value)
                {
                    if(isset($input['attribute'][$value['variation_id']]))
                    {
                        $attributes[]=array($value['name'] => $input['attribute'][$value['variation_id']]);
                    }
                    else
                    {
                        $attributes[]=array($value['name'] => $value['value']);
                    }
                }
            }
        }

        return $attributes;
    }

    private function GetSimpleProductAttributes($pp,$combination,$cpid,$cpvid,$attr)
    {
        $ppv=GetProductPrice($combination->combination_product_id,$combination->combination_variation_id,$pp->group_id);
        $cbattributes=array();
        $variations=$ppv->productVariations();
        if($variations)
        {
            foreach ($variations as $key => $value)
            {
                if(isset($attr[$cpid][$value['variation_id']]))
                {
                    $cbattributes[]=array($value['name'] => $attr[$cpid][$value['variation_id']]);
                }
                else
                {
                    $cbattributes[]=array(
                        $value['name'] => $value['value']);
                }
            }
        }
        return $cbattributes;
    }
    private function GetCustomProductAttributes($pp,$combination,$cpid,$cpvid,$attr)
    {
        $ppv=GetProductPrice($combination->combination_product_id,$combination->combination_variation_id,$pp->group_id);
        $combination_variation_id=$combination->combination_variation_id;
        $cbattributes=array();
        $variations=$ppv->productVariations();
        if($variations)
        {   //printe($variations,$attr[$cpid][$combination_variation_id]);
            foreach ($variations as $key => $value)
            {
                if(isset($attr[$cpid][$combination_variation_id][$value['variation_id']]))
                {
                    $cbattributes[]=array($value['name'] => $attr[$cpid][$combination_variation_id][$value['variation_id']]);
                }
                else
                {
                    $cbattributes[]=array(
                        $value['name'] => $value['value']);
                }
            }
        }
        return $cbattributes;
    }
}
