<?php
/**
 * Created by PhpStorm.
 * User: xuhu
 * Date: 15/9/14
 * Time: 下午6:04
 */

namespace Auto\Bundle\AdminBundle\Controller;
use Auto\Bundle\ManagerBundle\Entity\AuthMember;
use Auto\Bundle\ManagerBundle\Form\AuthMemberType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Auto\Bundle\ManagerBundle\Entity\Area;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @Route("/regionOperator")
 */
class RegionOperatorController extends Controller {

    const PER_PAGE = 20;

    /**
     * @Route("/list/{page}", methods="GET", name="auto_admin_region_operator_list",
     * requirements={"page"="\d+"},
     * defaults={"page"=1})
     * @Template()
     */
    public function listAction(Request $req,$page = 1)
    {
        $region = $this->getDoctrine()
            ->getRepository('AutoManagerBundle:Region')
            ->findOneBy(['member'=>$this->getUser()]);
        $areaIds = array();
        if(!empty($region)){
            $oAreas = $region->getAreas();
            foreach ($oAreas as $oArea) {
                $child1 = $oArea->getChildren()->toArray();
                if(empty($child1)){
                    $areaIds[] = $oArea->getId();
                }else{
                    foreach ($child1 as $c1) {
                        $child2 = $c1->getChildren()->toArray();
                        if(empty($child2)){
                            $areaIds[] = $c1->getId();
                        }else{
                            foreach ($child2 as $c2) {
                                $areaIds[] = $c2->getId();
                            }
                        }
                    }
                }
            }
        }
        $qbo =
            $this
                ->getDoctrine()
                ->getRepository('AutoManagerBundle:Operator')
                ->createQueryBuilder('o');
        $operators = $qbo
            ->select('o')
            ->leftJoin('o.stations','rs')
            ->where($qbo->expr()->in('rs.area',$areaIds))
            ->getQuery()
            ->getResult() ;
        $mids = array();
        foreach ($operators as $operator) {
            $mids[] = $operator->getMember()->getId();
        }

        $mobile = $req->query->get('mobile');
        $name = $req->query->get('name');
        $qb =
            $this
                ->getDoctrine()
                ->getRepository('AutoManagerBundle:Member')
                ->createQueryBuilder('m');
        ;
        $qb->where($qbo->expr()->in('m.id',$mids));
        if($mobile){
            $qb
                ->andWhere($qb->expr()->eq('m.mobile', ':mobile'))
                ->setParameter('mobile', $mobile);
        }
        if($name){
            $qb
                ->andWhere($qb->expr()->eq('m.name', ':name'))
                ->setParameter('name', $name);
        }
        $members =
            new Paginator(
                $qb
                    ->select('m')
                    ->andWhere($qb->expr()->like('m.roles', ':role'))
                    ->setParameter('role',"%".\Auto\Bundle\ManagerBundle\Entity\Member::ROLE_OPERATE."%")
                    ->getQuery()
                    ->setMaxResults(self::PER_PAGE)
                    ->setFirstResult(self::PER_PAGE * ($page - 1))
            );
        $total = ceil(count($members) / self::PER_PAGE);
        $tempMember = array();
        foreach ($members as $key => $member) {
            $tempMember[$key]['id'] = $member->getId();
            $tempMember[$key]['name'] = $member->getName();
            $tempMember[$key]['mobile'] = $member->getMobile();
            $roles = $member->getRoles();
            $tempRoleArray = array();
            if(in_array(\Auto\Bundle\ManagerBundle\Entity\Member::ROLE_ADMIN,$roles)){
                $tempRoleArray[] = '管理员';
            }
            if(in_array(\Auto\Bundle\ManagerBundle\Entity\Member::ROLE_COO,$roles)){
                $tempRoleArray[] = '运营总监';
            }
            if(in_array(\Auto\Bundle\ManagerBundle\Entity\Member::ROLE_OPERATE,$roles)){
                $tempRoleArray[] = '运营专员';
            }
            if(in_array(\Auto\Bundle\ManagerBundle\Entity\Member::ROLE_MARKET,$roles)){
                $tempRoleArray[] = '市场主管';
            }
            if(in_array(\Auto\Bundle\ManagerBundle\Entity\Member::ROLE_FINANCE,$roles)){
                $tempRoleArray[] = '财务主管';
            }
            if(in_array(\Auto\Bundle\ManagerBundle\Entity\Member::ROLE_SERVER,$roles)){
                $tempRoleArray[] = '客服主管';
            }
            $tempMember[$key]['roles'] = implode('、',$tempRoleArray);
        }

        return ['members'=>$tempMember,'page'=>$page,'total'=>$total,'mobile'=>$mobile,'name'=>$name];

    }

    /**
     * @Route("/show/{id}", methods="GET", name="auto_admin_region_operator_show")
     * @Template()
     */
    public function showAction($id)
    {
        $member = $this->getDoctrine()
            ->getRepository('AutoManagerBundle:Member')
            ->find($id);
        if(empty($member)){
            return $this->render(
                "AutoAdminBundle:Default:message.html.twig",
                ['message'=>'无此用户!']
            );
        }
        $operator = $this->getDoctrine()
            ->getRepository('AutoManagerBundle:Operator')
            ->findOneBy(['member'=>$member]);
        if(empty($operator)){
            $operator = new \Auto\Bundle\ManagerBundle\Entity\Operator();
            $operator->setMember($member);
            $man = $this->getDoctrine()->getManager();
            $man->persist($operator);
            $man->flush();
        }
        $stationsTemp = $operator->getStations();
        $stations = array();
        $areaArr = array();
        $cityArr = array();
        $provinceArr = array();
        foreach ($stationsTemp as $key => $station) {
            $stations[$key]['id'] = $station->getId();
            $stations[$key]['name'] = $station->getName();
            $area = $station->getArea();
            $areaArr[] = $area->getId();
            $city = $area->getParent();
            $cityArr[] = $city->getId();
            $province = $city->getParent();
            $provinceArr[] = $province->getId();
            $stations[$key]['area'] = $province->getName().'-'.$city->getName().'-'.$area->getName();

        }
        $areacount = array(
                'province'=>count(array_unique($provinceArr)),
                'city'=>count(array_unique($cityArr)),
                'area'=>count(array_unique($areaArr)),
                'station'=>count($stationsTemp)
        );


        return ['operator'=>$operator,'stations'=>$stations,'areacount'=>$areacount];
    }

    /**
     * @Route("/del/{operator_id}/{station_id}/", methods="GET", name="auto_admin_region_operator_delete",requirements={"id"="\d+"})
     */
    public function deleteAction($operator_id,$station_id)
    {
        $operator=$this->getDoctrine()
            -> getRepository('AutoManagerBundle:Operator')
            ->find($operator_id);
        $station = $this->getDoctrine()
            ->getRepository('AutoManagerBundle:Station')
            ->find($station_id);
        $operator->removeStation($station);
        $man = $this->getDoctrine()->getManager();
        $man->persist($operator);
        $man->flush();

        return $this->redirect($this->generateUrl('auto_admin_region_operator_show',["id"=>$operator->getMember()->getId()]));
    }

    /**
     * @Route("/set/{id}", methods="GET", name="auto_admin_region_operator_station_set")
     * @Template()
     */
    public function setAction($id)
    {
        $member = $this->getDoctrine()
            ->getRepository('AutoManagerBundle:Member')
            ->find($id);
        if(empty($member)){
            return $this->render(
                "AutoAdminBundle:Default:message.html.twig",
                ['message'=>'无此用户!']
            );
        }
        $operator = $this->getDoctrine()
            ->getRepository('AutoManagerBundle:Operator')
            ->findOneBy(['member'=>$member]);
        if(empty($operator)){
            $operator = new \Auto\Bundle\ManagerBundle\Entity\Operator();
            $operator->setMember($member);
            $man = $this->getDoctrine()->getManager();
            $man->persist($operator);
            $man->flush();
        }

        return ['operator'=>$operator];
    }

    /**
     * @Route("/set/{id}", methods="POST", name="auto_admin_region_operator_station_set_do")
     * @Template()
     */
    public function setDoAction(Request $req,$id)
    {
        $operator = $this->getDoctrine()
            ->getRepository('AutoManagerBundle:Operator')
            ->find($id);
        if(empty($operator)){
            return $this->render(
                "AutoAdminBundle:Default:message.html.twig",
                ['message'=>'非法操作!']
            );
        }

        $arrStationIds = $req->request->get('rental_station');
        $stations = $operator->getStations();
        $stationsExist = array();
        foreach ($stations as $s) {
            $stationsExist[] = $s->getId();
        }
        foreach ($arrStationIds as $rental_station_id) {
            $rental_station_id = intval($rental_station_id);
            $station = $this->getDoctrine()
                ->getRepository('AutoManagerBundle:Station')
                ->find($rental_station_id);
            if(empty($station)){
                continue;
            }
            if($stationsExist && in_array($rental_station_id,$stationsExist)){
                continue;
            }
            $operator->addStation($station);
            $man = $this->getDoctrine()->getManager();
            $man->persist($operator);
            $man->flush();
        }
        return $this->redirect($this->generateUrl('auto_admin_region_operator_show',["id"=>$operator->getMember()->getId()]));

    }





}